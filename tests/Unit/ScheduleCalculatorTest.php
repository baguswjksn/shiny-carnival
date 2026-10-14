<?php

namespace Tests\Unit;

use App\Services\ScheduleCalculator;
use PHPUnit\Framework\TestCase;

class ScheduleCalculatorTest extends TestCase
{
    private ScheduleCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new ScheduleCalculator();
    }

    private function days(array $actuals = []): array
    {
        $days = $this->calc->distribute('2026-10-01', '2026-10-03', 100);
        foreach ($days as $i => &$d) {
            $d['actual_qty'] = $actuals[$i] ?? null;
        }

        return $days;
    }

    public function test_distribute_puts_remainder_on_last_day(): void
    {
        $out = array_column($this->calc->distribute('2026-10-01', '2026-10-03', 100), 'base_target');
        $this->assertSame([33, 33, 34], $out);
    }

    public function test_single_day_range(): void
    {
        $out = $this->calc->distribute('2026-10-01', '2026-10-01', 50);
        $this->assertSame([['work_date' => '2026-10-01', 'base_target' => 50]], $out);
    }

    public function test_shortfall_does_not_pile_onto_next_day_but_adds_a_day(): void
    {
        // 100 over 3 days (33/33/34), day 1 only 20 -> daily targets stay, one extra day for the rest
        $r = $this->calc->recalculate($this->days([20]));
        $this->assertSame([33, 33, 34, 13], array_column($r['days'], 'target_qty'));
        $this->assertSame(1, $r['added_days']);
        $this->assertSame('2026-10-04', $r['finish_date']);
        $this->assertTrue($r['days'][3]['is_extension']);
    }

    public function test_example_target_20_over_two_days(): void
    {
        $days = $this->calc->distribute('2026-10-01', '2026-10-02', 20);
        $days[0]['actual_qty'] = 5;
        $days[1]['actual_qty'] = null;

        $r = $this->calc->recalculate($days);
        $this->assertSame([10, 10, 5], array_column($r['days'], 'target_qty'));
        $this->assertSame('2026-10-03', $r['finish_date']);

        // day 2 also short by 5 -> 10 left = exactly one more full day (no bigger quota)
        $days[1]['actual_qty'] = 5;
        $r = $this->calc->recalculate($days);
        $this->assertSame([10, 10, 10], array_column($r['days'], 'target_qty'));
        $this->assertSame('2026-10-03', $r['finish_date']);
    }

    public function test_surplus_shrinks_the_tail_not_the_next_day(): void
    {
        $r = $this->calc->recalculate($this->days([40]));
        $this->assertSame([33, 33, 27], array_column($r['days'], 'target_qty'));

        $r = $this->calc->recalculate($this->days([40]), null, false);
        $this->assertSame([33, 33, 34], array_column($r['days'], 'target_qty'));
    }

    public function test_shortfall_on_last_day_extends_range(): void
    {
        $r = $this->calc->recalculate($this->days([33, 33, 10]));
        $this->assertSame(1, $r['added_days']);
        $this->assertSame('2026-10-04', $r['finish_date']);
        $this->assertSame(24, $r['days'][3]['target_qty']);
        $this->assertTrue($r['days'][3]['is_extension']);
    }

    public function test_extension_respects_daily_capacity(): void
    {
        $r = $this->calc->recalculate($this->days([33, 33, 10]), 10);
        $this->assertSame([10, 10, 4], array_slice(array_column($r['days'], 'target_qty'), 3));
        $this->assertSame('2026-10-06', $r['finish_date']);
    }

    public function test_recalculate_is_idempotent(): void
    {
        $first  = $this->calc->recalculate($this->days([33, 33, 10]), 10);
        $second = $this->calc->recalculate($first['days'], 10);
        $this->assertSame($first['days'], $second['days']);
    }

    public function test_unneeded_extension_days_are_removed_after_correction(): void
    {
        $first = $this->calc->recalculate($this->days([33, 33, 10]));
        $days = $first['days'];
        $days[2]['actual_qty'] = 34; // admin corrects the last real day
        $second = $this->calc->recalculate($days);

        $this->assertSame(1, $second['removed_days']);
        $this->assertSame('2026-10-03', $second['finish_date']);
    }
}
