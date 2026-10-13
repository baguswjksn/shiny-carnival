<?php

namespace App\Services;

use App\Exceptions\ScheduleLockedException;
use App\Models\Order;
use App\Models\Schedule;
use App\Models\ScheduleDay;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

/**
 * Everything about where schedules sit on a line: locking, ordering,
 * pushing later schedules back, syncing day rows, order status.
 * Call these inside a DB transaction.
 */
class ScheduleLayoutService
{
    /** Serialise all changes on one line. Always lock lines in ascending id order. */
    public function lockLine(int $lineId): Collection
    {
        return Schedule::where('line_id', $lineId)->orderBy('id')->lockForUpdate()->get();
    }

    /** sequence_no = position by start_date within the line. */
    public function resequence(int $lineId): void
    {
        $i = 1;
        Schedule::where('line_id', $lineId)->orderBy('start_date')->orderBy('id')->get()
            ->each(function (Schedule $s) use (&$i) {
                if ($s->sequence_no !== $i) {
                    $s->sequence_no = $i;
                    $s->save();
                }
                $i++;
            });
    }

    /**
     * After $changed finished later, push following schedules on the same line
     * so none overlaps. Existing gaps are kept (a schedule only moves if it overlaps).
     *
     * @return \Illuminate\Support\Collection<int, Schedule> schedules that moved
     */
    public function cascade(Schedule $changed): \Illuminate\Support\Collection
    {
        $following = Schedule::where('line_id', $changed->line_id)
            ->where('sequence_no', '>', $changed->sequence_no)
            ->orderBy('sequence_no')
            ->get();

        $prevFinish = $changed->finish_date->copy();
        $shifted = collect();

        foreach ($following as $s) {
            if ($s->start_date->lte($prevFinish)) {
                $delta = (int) $s->start_date->diffInDays($prevFinish->copy()->addDay(), false);
                $this->shift($s, $delta);
                $shifted->push($s);
            }
            $prevFinish = $s->finish_date->copy();
        }

        return $shifted;
    }

    /** Move a whole schedule (dates + all its days) later by $delta days. */
    public function shift(Schedule $s, int $delta): void
    {
        if ($delta <= 0) {
            return;
        }

        if ($s->hasActuals()) {
            $s->loadMissing('order');
            $no = $s->order?->order_no ?? "#{$s->id}";
            throw new ScheduleLockedException("Schedule {$no} sudah berjalan (ada aktual) dan tidak bisa digeser.");
        }

        // Latest day first so the (schedule_id, work_date) unique key never collides.
        $days = ScheduleDay::where('schedule_id', $s->id)->orderByDesc('work_date')->get();
        foreach ($days as $d) {
            $d->work_date = $d->work_date->copy()->addDays($delta);
            $d->save();
        }

        $s->start_date = $s->start_date->copy()->addDays($delta);
        $s->finish_date = $s->finish_date->copy()->addDays($delta);
        $s->planned_finish_date = $s->planned_finish_date->copy()->addDays($delta);
        $s->version = $s->version + 1;
        $s->save();
    }

    /**
     * Make schedule_days match $rows exactly (update, insert, delete by work_date).
     *
     * @param array<int, array<string, mixed>> $rows output of ScheduleCalculator::recalculate()['days']
     */
    public function syncDays(Schedule $schedule, array $rows): void
    {
        $existing = ScheduleDay::where('schedule_id', $schedule->id)->get()
            ->keyBy(fn (ScheduleDay $d) => $d->work_date->toDateString());

        $keep = [];
        foreach ($rows as $r) {
            $keep[] = $r['work_date'];
            $payload = Arr::only($r, ['work_date', 'base_target', 'carry_in', 'target_qty', 'actual_qty', 'is_extension']);

            if ($existing->has($r['work_date'])) {
                $existing[$r['work_date']]->update($payload);
            } else {
                ScheduleDay::create($payload + ['schedule_id' => $schedule->id]);
            }
        }

        ScheduleDay::where('schedule_id', $schedule->id)->whereNotIn('work_date', $keep)->delete();
    }

    public function refreshOrderStatus(int $orderId): void
    {
        $order = Order::lockForUpdate()->find($orderId);
        if (! $order) {
            return;
        }

        $schedules = Schedule::where('order_id', $orderId)->get(['id', 'status', 'target_qty']);

        $status = Order::STATUS_OPEN;
        if ($schedules->isNotEmpty()) {
            $status = Order::STATUS_SCHEDULED;
            $allDone = $schedules->every(fn (Schedule $s) => $s->status === Schedule::STATUS_DONE);
            if ($allDone && $schedules->sum('target_qty') >= $order->total_qty) {
                $status = Order::STATUS_DONE;
            }
        }

        if ($order->status !== $status) {
            $order->status = $status;
            $order->save();
        }
    }
}
