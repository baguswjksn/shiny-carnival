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
     * Put every schedule on the line where it belongs: as close to its planned start as possible,
     * but never overlapping the schedule before it. Pushes later schedules back when an earlier one
     * grew, and pulls them forward again (never before planned_start_date) when it shrank or was
     * removed. Schedules that already have actuals never move.
     *
     * @return \Illuminate\Support\Collection<int, Schedule> schedules that moved
     */
    public function reflow(int $lineId): \Illuminate\Support\Collection
    {
        $schedules = Schedule::where('line_id', $lineId)->orderBy('sequence_no')->orderBy('id')->get();

        $shifted = collect();
        $prevFinish = null;

        foreach ($schedules as $s) {
            $desired = ($s->planned_start_date ?? $s->start_date)->copy();
            if ($prevFinish !== null && $desired->lte($prevFinish)) {
                $desired = $prevFinish->copy()->addDay();
            }

            $delta = (int) $s->start_date->diffInDays($desired, false);

            if ($delta > 0 || ($delta < 0 && ! $s->hasActuals())) {
                $this->shift($s, $delta); // throws if a running schedule would have to be pushed
                $shifted->push($s);
            }

            $prevFinish = $s->finish_date->copy();
        }

        return $shifted;
    }

    /**
     * Move a whole schedule (dates + all its days) by $delta days (later if positive, earlier if
     * negative). planned_* dates are left alone: they are the original plan.
     */
    public function shift(Schedule $s, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        if ($s->hasActuals()) {
            $s->loadMissing('order');
            $no = $s->order?->order_no ?? "#{$s->id}";
            throw new ScheduleLockedException("Schedule {$no} sudah berjalan (ada aktual) dan tidak bisa digeser.");
        }

        // Move the leading edge first so the (schedule_id, work_date) unique key never collides.
        $days = ScheduleDay::where('schedule_id', $s->id)
            ->orderBy('work_date', $delta > 0 ? 'desc' : 'asc')
            ->get();
        foreach ($days as $d) {
            $d->work_date = $d->work_date->copy()->addDays($delta);
            $d->save();
        }

        $s->start_date = $s->start_date->copy()->addDays($delta);
        $s->finish_date = $s->finish_date->copy()->addDays($delta);
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
