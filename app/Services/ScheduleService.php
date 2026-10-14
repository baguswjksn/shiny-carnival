<?php

namespace App\Services;

use App\Exceptions\ScheduleLockedException;
use App\Exceptions\StaleVersionException;
use App\Models\Line;
use App\Models\Order;
use App\Models\Schedule;
use App\Models\ScheduleDay;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleService
{
    public function __construct(
        private ScheduleCalculator $calc,
        private ScheduleLayoutService $layout,
    ) {
    }

    /** @param array{from?: ?string, to?: ?string, line_id?: ?int, status?: ?string} $filters */
    public function list(array $filters = []): Collection
    {
        return Schedule::query()
            ->with(['order', 'line', 'days'])
            ->when($filters['line_id'] ?? null, fn ($q, $v) => $q->where('line_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('finish_date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('start_date', '<=', $v))
            ->orderBy('line_id')
            ->orderBy('sequence_no')
            ->get();
    }

    public function create(array $data): Schedule
    {
        return DB::transaction(function () use ($data) {
            $lineId = (int) $data['line_id'];

            $this->layout->lockLine($lineId);
            $order = Order::lockForUpdate()->findOrFail($data['order_id']);

            $this->assertRange($data['start_date'], $data['finish_date']);
            $this->assertQuota($order, (int) $data['target_qty'], null);
            [$start, $finish] = $this->placeOnLine($lineId, $data['start_date'], $data['finish_date'], null);

            $schedule = Schedule::create([
                'order_id'            => $order->id,
                'line_id'             => $lineId,
                'start_date'          => $start,
                'finish_date'         => $finish,
                'planned_start_date'  => $data['start_date'],
                'planned_finish_date' => $data['finish_date'],
                'target_qty'          => (int) $data['target_qty'],
                'sequence_no'         => 0,
            ]);

            $this->writeInitialDays($schedule);
            $this->layout->resequence($lineId);
            $this->layout->refreshOrderStatus($order->id);

            return $schedule->fresh(['order', 'line', 'days']);
        });
    }

    /** Only schedules without actuals can be edited; days are regenerated from scratch. */
    public function update(Schedule $schedule, array $data): Schedule
    {
        return DB::transaction(function () use ($schedule, $data) {
            $newLineId = (int) $data['line_id'];

            $lineIds = collect([$schedule->line_id, $newLineId])->unique()->sort()->values();
            foreach ($lineIds as $id) {
                $this->layout->lockLine($id);
            }

            $schedule = Schedule::lockForUpdate()->findOrFail($schedule->id);
            if ($schedule->version !== (int) $data['version']) {
                throw new StaleVersionException();
            }
            $this->assertEditable($schedule);

            $oldLineId = $schedule->line_id;
            $oldOrderId = $schedule->order_id;
            $order = Order::lockForUpdate()->findOrFail($data['order_id']);

            $this->assertRange($data['start_date'], $data['finish_date']);
            $this->assertQuota($order, (int) $data['target_qty'], $schedule->id);
            [$start, $finish] = $this->placeOnLine($newLineId, $data['start_date'], $data['finish_date'], $schedule->id);

            $schedule->fill([
                'order_id'            => $order->id,
                'line_id'             => $newLineId,
                'start_date'          => $start,
                'finish_date'         => $finish,
                'planned_start_date'  => $data['start_date'],
                'planned_finish_date' => $data['finish_date'],
                'target_qty'          => (int) $data['target_qty'],
                'actual_qty'          => 0,
                'status'              => Schedule::STATUS_PLANNED,
            ]);
            $schedule->version = $schedule->version + 1;
            $schedule->save();

            ScheduleDay::where('schedule_id', $schedule->id)->delete();
            $this->writeInitialDays($schedule->refresh());

            foreach (array_unique([$oldLineId, $newLineId]) as $lid) {
                $this->layout->resequence($lid);
                $this->layout->reflow($lid);
            }
            foreach (array_unique([$oldOrderId, $order->id]) as $oid) {
                $this->layout->refreshOrderStatus($oid);
            }

            return $schedule->fresh(['order', 'line', 'days']);
        });
    }

    public function delete(Schedule $schedule): void
    {
        DB::transaction(function () use ($schedule) {
            $this->layout->lockLine($schedule->line_id);
            $schedule = Schedule::lockForUpdate()->findOrFail($schedule->id);
            $this->assertEditable($schedule);

            $lineId = $schedule->line_id;
            $orderId = $schedule->order_id;
            $schedule->delete(); // schedule_days cascade

            $this->layout->resequence($lineId);
            $this->layout->reflow($lineId); // schedules behind it move forward again
            $this->layout->refreshOrderStatus($orderId);
        });
    }

    // ---------------------------------------------------------------- internals

    private function writeInitialDays(Schedule $schedule): void
    {
        $capacity = Line::find($schedule->line_id)?->daily_capacity;

        $rows = array_map(
            fn (array $r) => $r + ['actual_qty' => null, 'is_extension' => false],
            $this->calc->distribute(
                $schedule->start_date->toDateString(),
                $schedule->finish_date->toDateString(),
                $schedule->target_qty
            )
        );

        $result = $this->calc->recalculate($rows, $capacity, (bool) config('schedule.apply_surplus', true));
        $this->layout->syncDays($schedule, $result['days']);
    }

    private function assertEditable(Schedule $schedule): void
    {
        if ($schedule->hasActuals()) {
            throw new ScheduleLockedException('Schedule sudah memiliki aktual output, tidak bisa diubah atau dihapus.');
        }
    }

    private function assertRange(string $start, string $finish): void
    {
        $days = (int) CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($finish)) + 1;
        $max = (int) config('schedule.max_range_days', 366);

        if ($days > $max) {
            throw ValidationException::withMessages(['finish_date' => "Range schedule maksimal {$max} hari."]);
        }
    }

    private function assertQuota(Order $order, int $qty, ?int $excludeScheduleId): void
    {
        $used = (int) Schedule::where('order_id', $order->id)
            ->when($excludeScheduleId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->sum('target_qty');

        $remaining = $order->total_qty - $used;
        if ($qty > $remaining) {
            throw ValidationException::withMessages([
                'target_qty' => "Qty target melebihi sisa qty order ({$remaining}).",
            ]);
        }
    }

    /**
     * Find a free spot on the line. If the requested range overlaps another schedule, the start
     * moves to the day after that schedule finishes (duration stays the same) and the check
     * repeats until nothing overlaps.
     *
     * @return array{0: string, 1: string} [start_date, finish_date]
     */
    private function placeOnLine(int $lineId, string $start, string $finish, ?int $excludeScheduleId): array
    {
        $from = CarbonImmutable::parse($start);
        $to   = CarbonImmutable::parse($finish);
        $span = (int) $from->diffInDays($to);

        while (true) {
            $clashEnd = Schedule::where('line_id', $lineId)
                ->overlapping($from->toDateString(), $to->toDateString())
                ->when($excludeScheduleId, fn ($q, $id) => $q->where('id', '!=', $id))
                ->max('finish_date');

            if ($clashEnd === null) {
                return [$from->toDateString(), $to->toDateString()];
            }

            $from = CarbonImmutable::parse($clashEnd)->addDay();
            $to   = $from->addDays($span);
        }
    }
}
