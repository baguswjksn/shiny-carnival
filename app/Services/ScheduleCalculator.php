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
     * Rebuild carry-over and effective targets from day 1. Idempotent: running it again on its
     * own output (with the same actuals) returns the same result.
     *
     * Input rows: work_date, base_target, actual_qty (null = not entered), is_extension (optional).
     *
     * Rules:
     *  - effective target = max(base_target + carry_in, 0)
     *  - after a day with actual: carry = (base_target + carry_in) - actual
     *    (negative = surplus; set $applySurplus=false to ignore surplus)
     *  - a day without actual carries nothing forward (days must be filled in order)
     *  - if shortfall remains after the last day, extension days are appended
     *    (capped by $dailyCapacity when set)
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

        $cap = ($dailyCapacity !== null && $dailyCapacity > 0) ? $dailyCapacity : null;
        usort($days, fn (array $a, array $b) => strcmp($a['work_date'], $b['work_date']));

        $rows = [];
        $carries = [];
        $carry = 0;

        foreach ($days as $d) {
            [$row, $carry] = $this->buildDay(
                $d['work_date'],
                (int) $d['base_target'],
                isset($d['actual_qty']) ? (int) $d['actual_qty'] : null,
                (bool) ($d['is_extension'] ?? false),
                $carry,
                $cap,
                $applySurplus
            );
            $rows[] = $row;
            $carries[] = $carry;
        }

        // Drop unused trailing extension days (e.g. admin corrected an actual upwards).
        $removed = 0;
        while (count($rows) > 1) {
            $last = $rows[array_key_last($rows)];
            if (! $last['is_extension'] || $last['actual_qty'] !== null || $last['target_qty'] !== 0) {
                break;
            }
            array_pop($rows);
            array_pop($carries);
            $removed++;
        }
        $carry = $carries[array_key_last($carries)];

        // Append extension days while a shortfall is left over.
        $added = 0;
        $date = CarbonImmutable::parse($rows[array_key_last($rows)]['work_date']);
        while ($carry > 0) {
            if (++$added > self::MAX_EXTENSION_DAYS) {
                throw new InvalidArgumentException('Extension exceeds ' . self::MAX_EXTENSION_DAYS . ' days.');
            }
            $date = $date->addDay();
            [$row, $carry] = $this->buildDay($date->toDateString(), 0, null, true, $carry, $cap, $applySurplus);
            $rows[] = $row;
        }

        return [
            'days'         => $rows,
            'finish_date'  => $rows[array_key_last($rows)]['work_date'],
            'added_days'   => $added,
            'removed_days' => $removed,
            'actual_total' => array_sum(array_map(fn (array $r) => $r['actual_qty'] ?? 0, $rows)),
        ];
    }

    /** @return array{0: array<string, mixed>, 1: int} [row, carry_out] */
    private function buildDay(
        string $date,
        int $base,
        ?int $actual,
        bool $isExtension,
        int $carryIn,
        ?int $cap,
        bool $applySurplus
    ): array {
        $raw = $base + $carryIn;
        $target = max($raw, 0);
        $capped = $isExtension && $cap !== null;
        if ($capped) {
            $target = min($target, $cap);
        }

        if ($actual !== null) {
            $carryOut = $raw - $actual;
            if ($carryOut < 0 && ! $applySurplus) {
                $carryOut = 0;
            }
        } else {
            // Only capped extension days pass leftover on; normal days wait for their actual.
            $carryOut = $capped ? max($raw - $target, 0) : 0;
        }

        return [[
            'work_date'    => $date,
            'base_target'  => $base,
            'carry_in'     => $carryIn,
            'target_qty'   => $target,
            'actual_qty'   => $actual,
            'is_extension' => $isExtension,
        ], $carryOut];
    }
}
