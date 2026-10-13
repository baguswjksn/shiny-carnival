<?php

namespace App\Services;

use App\Exceptions\StaleVersionException;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records actual output and balances the schedule:
 * shortfall carries into the next day, the range may grow,
 * and later schedules on the same line are pushed back.
 */
class ScheduleBalancingService
{
    public function __construct(
        private ScheduleCalculator $calc,
        private ScheduleLayoutService $layout,
    ) {
    }

    /**
     * @return array{schedule: Schedule, shifted: Collection, finish_delta: int}
     */
    public function recordActual(Schedule $schedule, string $date, int $actualQty, int $version): array
    {
        return DB::transaction(function () use ($schedule, $date, $actualQty, $version) {
            $this->layout->lockLine($schedule->line_id);

            $schedule = Schedule::lockForUpdate()->findOrFail($schedule->id);
            if ($schedule->version !== $version) {
                throw new StaleVersionException();
            }

            $days = $schedule->days()->get();

            if (! $days->contains(fn ($d) => $d->work_date->toDateString() === $date)) {
                throw ValidationException::withMessages(['date' => 'Tanggal tersebut tidak ada di schedule ini.']);
            }

            // Actuals are entered in order so carry-over stays consistent.
            foreach ($days as $d) {
                $dDate = $d->work_date->toDateString();
                if ($dDate >= $date) {
                    break;
                }
                if ($d->actual_qty === null) {
                    throw ValidationException::withMessages([
                        'actual_qty' => 'Isi aktual tanggal ' . $dDate . ' terlebih dahulu.',
                    ]);
                }
            }

            $rows = $days->map(fn ($d) => [
                'work_date'    => $d->work_date->toDateString(),
                'base_target'  => $d->base_target,
                'actual_qty'   => $d->work_date->toDateString() === $date ? $actualQty : $d->actual_qty,
                'is_extension' => $d->is_extension,
            ])->all();

            $oldFinish = $schedule->finish_date->toDateString();

            $result = $this->calc->recalculate(
                $rows,
                $schedule->line->daily_capacity,
                (bool) config('schedule.apply_surplus', true)
            );
            $this->layout->syncDays($schedule, $result['days']);

            $all = collect($result['days']);
            $filled = $all->filter(fn ($r) => $r['actual_qty'] !== null)->count();
            $status = match (true) {
                $filled === 0            => Schedule::STATUS_PLANNED,
                $filled === $all->count() => Schedule::STATUS_DONE,
                default                  => Schedule::STATUS_RUNNING,
            };

            $schedule->fill([
                'finish_date' => $result['finish_date'],
                'actual_qty'  => $result['actual_total'],
                'status'      => $status,
            ]);
            $schedule->version = $schedule->version + 1;
            $schedule->save();

            $delta = (int) CarbonImmutable::parse($oldFinish)
                ->diffInDays(CarbonImmutable::parse($result['finish_date']), false);

            $shifted = collect();
            if ($delta > 0) {
                $shifted = $this->layout->cascade($schedule);
            }

            $this->layout->refreshOrderStatus($schedule->order_id);

            $schedule->load(['order', 'line', 'days']);
            $shifted->each(fn (Schedule $s) => $s->load(['order', 'line', 'days']));

            return [
                'schedule'     => $schedule,
                'shifted'      => $shifted,
                'finish_delta' => $delta,
            ];
        });
    }
}
