<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Pure scheduling math: no database, no framework state.
 * ScheduleService / ScheduleBalancingService load rows, call this, then persist the result.
 */
class ScheduleCalculator
{
    private const MAX_EXTENSION_DAYS = 366;

    /**
     * Initial even split. The remainder goes on the last day of the range.
     *
     * @return array<int, array{work_date: string, base_target: int}>
     */
    public function distribute(string $startDate, string $finishDate, int $totalQty): array
    {
        $from = CarbonImmutable::parse($startDate)->startOfDay();
        $to   = CarbonImmutable::parse($finishDate)->startOfDay();

        if ($to->lt($from)) {
            throw new InvalidArgumentException('finish_date must be on or after start_date.');
        }
        if ($totalQty < 1) {
            throw new InvalidArgumentException('total qty must be at least 1.');
        }

        $count = (int) $from->diffInDays($to) + 1;
        $base  = intdiv($totalQty, $count);
        $rest  = $totalQty % $count;

        $days = [];
        for ($i = 0; $i < $count; $i++) {
            $days[] = [
                'work_date'   => $from->addDays($i)->toDateString(),
                'base_target' => $base + ($i === $count - 1 ? $rest : 0),
            ];
        }

        return $days;
    }

    /**
     * Rebuild targets from day 1. Idempotent: running it again on its own output
     * (with the same actuals) returns the same result.
     *
     * Input rows: work_date, base_target, actual_qty (null = not entered), is_extension (optional).
     *
     * Rules:
     *  - the daily target of the original range never grows: target = min(base_target, remaining)
     *  - shortfall is NOT piled onto the next day; it stays in "remaining" and is paid off by
     *    extension days appended after the last day, each at the original daily rate
     *    (capped by $dailyCapacity when set), so the range gets longer instead of the quota bigger
     *  - surplus (actual above target) reduces "remaining"; set $applySurplus=false to ignore it
     *  - a day without actual is projected as "target met"
     *  - trailing extension days that are no longer needed are dropped
     *
     * @param  array<int, array<string, mixed>> $days
     * @return array{days: array<int, array<string, mixed>>, finish_date: string,
     *               added_days: int, removed_days: int, actual_total: int}
     */
    public function recalculate(array $days, ?int $dailyCapacity = null, bool $applySurplus = true): array
    {
        if ($days === []) {
            throw new InvalidArgumentException('A schedule needs at least one day.');
        }

        usort($days, fn (array $a, array $b) => strcmp($a['work_date'], $b['work_date']));

        $normal = array_values(array_filter($days, fn (array $d) => ! ($d['is_extension'] ?? false)));
        if ($normal === []) {
            throw new InvalidArgumentException('A schedule needs at least one non-extension day.');
        }

        $remaining = (int) array_sum(array_map(fn (array $d) => (int) $d['base_target'], $normal));

        // Daily rate of the original plan (the last day may hold the remainder, so use the first day).
        $rate = (int) $normal[0]['base_target'];
        if ($rate < 1) {
            $rate = max(1, (int) max(array_column($normal, 'base_target')));
        }
        $extRate = ($dailyCapacity !== null && $dailyCapacity > 0) ? min($rate, $dailyCapacity) : $rate;

        $rows = [];
        $removed = 0;

        foreach ($days as $d) {
            $isExt = (bool) ($d['is_extension'] ?? false);
            $actual = isset($d['actual_qty']) ? (int) $d['actual_qty'] : null;

            // Extension day nobody needs any more (and nothing entered on it): drop it.
            if ($isExt && $actual === null && $remaining <= 0) {
                $removed++;
                continue;
            }

            $rows[] = $this->buildDay($d['work_date'], $isExt ? 0 : (int) $d['base_target'], $actual, $isExt, $extRate, $applySurplus, $remaining);
        }

        // Shortfall left after the last day -> append extension days at the daily rate.
        $added = 0;
        $date = CarbonImmutable::parse($rows[array_key_last($rows)]['work_date']);
        while ($remaining > 0) {
            if (++$added > self::MAX_EXTENSION_DAYS) {
                throw new InvalidArgumentException('Extension exceeds ' . self::MAX_EXTENSION_DAYS . ' days.');
            }
            $date = $date->addDay();
            $rows[] = $this->buildDay($date->toDateString(), 0, null, true, $extRate, $applySurplus, $remaining);
        }

        return [
            'days'         => $rows,
            'finish_date'  => $rows[array_key_last($rows)]['work_date'],
            'added_days'   => $added,
            'removed_days' => $removed,
            'actual_total' => array_sum(array_map(fn (array $r) => $r['actual_qty'] ?? 0, $rows)),
        ];
    }

    /**
     * Builds one day and reduces $remaining (by reference) by what that day delivers
     * (actual if entered, otherwise its target as a projection).
     *
     * @return array<string, mixed>
     */
    private function buildDay(
        string $date,
        int $base,
        ?int $actual,
        bool $isExtension,
        int $extRate,
        bool $applySurplus,
        int &$remaining
    ): array {
        $left = max($remaining, 0);
        $target = $isExtension ? min($extRate, $left) : min($base, $left);

        if ($actual === null) {
            $done = $target;
        } else {
            $done = $applySurplus ? $actual : min($actual, $target);
        }
        $remaining -= $done;

        return [
            'work_date'    => $date,
            'base_target'  => $base,
            // normal day: negative only when surplus shrinks it; extension day: its whole target
            'carry_in'     => $target - $base,
            'target_qty'   => $target,
            'actual_qty'   => $actual,
            'is_extension' => $isExtension,
        ];
    }
}
