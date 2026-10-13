<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Models\Order;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(\App\Models\User::factory()->create());
    }

    private function line(string $code = 'L1'): Line
    {
        return Line::create(['code' => $code, 'name' => "Line $code"]);
    }

    private function order(string $no, int $qty): Order
    {
        return Order::create(['order_no' => $no, 'buyer' => 'B', 'style' => 'S', 'total_qty' => $qty]);
    }

    private function createSchedule(Order $o, Line $l, string $start, string $finish, int $qty)
    {
        return $this->postJson('/api/schedules', [
            'order_id'    => $o->id,
            'line_id'     => $l->id,
            'start_date'  => $start,
            'finish_date' => $finish,
            'target_qty'  => $qty,
        ]);
    }

    public function test_create_schedule_splits_target_and_puts_remainder_on_last_day(): void
    {
        $res = $this->createSchedule($this->order('PO1', 100), $this->line(), '2026-10-01', '2026-10-03', 100);

        $res->assertCreated()
            ->assertJsonPath('data.schedule.target_per_day', 33)
            ->assertJsonPath('data.schedule.last_day_target', 34);
        $this->assertDatabaseCount('schedule_days', 3);
    }

    public function test_overlap_on_same_line_is_rejected(): void
    {
        $line = $this->line();
        $this->createSchedule($this->order('PO1', 100), $line, '2026-10-01', '2026-10-05', 100)->assertCreated();

        $this->createSchedule($this->order('PO2', 100), $line, '2026-10-05', '2026-10-08', 100)
            ->assertStatus(409)
            ->assertJsonPath('reason', 'conflict');
    }

    public function test_target_cannot_exceed_remaining_order_qty(): void
    {
        $this->createSchedule($this->order('PO1', 100), $this->line(), '2026-10-01', '2026-10-03', 150)
            ->assertStatus(422)
            ->assertJsonValidationErrors('target_qty');
    }

    public function test_shortfall_carries_to_next_day(): void
    {
        $line = $this->line();
        $id = $this->createSchedule($this->order('PO1', 100), $line, '2026-10-01', '2026-10-03', 100)
            ->json('data.schedule.id');

        $res = $this->putJson("/api/schedules/$id/days/2026-10-01/actual", ['actual_qty' => 20, 'version' => 1]);

        $res->assertOk()
            ->assertJsonPath('data.schedule.days.1.target_qty', 46)
            ->assertJsonPath('data.schedule.days.2.target_qty', 34)
            ->assertJsonPath('data.schedule.status', 'running');
    }

    public function test_shortfall_on_last_day_extends_range_and_pushes_next_schedule(): void
    {
        $line = $this->line();
        $a = $this->createSchedule($this->order('PO1', 100), $line, '2026-10-01', '2026-10-03', 100)->json('data.schedule.id');
        $b = $this->createSchedule($this->order('PO2', 60), $line, '2026-10-04', '2026-10-06', 60)->json('data.schedule.id');

        $v = 1;
        foreach (['2026-10-01' => 33, '2026-10-02' => 33] as $date => $qty) {
            $v = $this->putJson("/api/schedules/$a/days/$date/actual", ['actual_qty' => $qty, 'version' => $v])
                ->assertOk()->json('data.schedule.version');
        }

        $res = $this->putJson("/api/schedules/$a/days/2026-10-03/actual", ['actual_qty' => 10, 'version' => $v]);

        $res->assertOk()
            ->assertJsonPath('data.schedule.finish_date', '2026-10-04')
            ->assertJsonPath('data.schedule.delay_days', 1)
            ->assertJsonPath('data.shifted.0.id', $b)
            ->assertJsonPath('data.shifted.0.start_date', '2026-10-05')
            ->assertJsonPath('data.shifted.0.finish_date', '2026-10-07');

        $this->assertSame('2026-10-05', Schedule::find($b)->start_date->toDateString());
        $this->assertDatabaseHas('schedule_days', ['schedule_id' => $b, 'work_date' => '2026-10-07']);
        $this->assertDatabaseMissing('schedule_days', ['schedule_id' => $b, 'work_date' => '2026-10-04']);
    }

    public function test_actuals_must_be_entered_in_order(): void
    {
        $id = $this->createSchedule($this->order('PO1', 100), $this->line(), '2026-10-01', '2026-10-03', 100)
            ->json('data.schedule.id');

        $this->putJson("/api/schedules/$id/days/2026-10-02/actual", ['actual_qty' => 30, 'version' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('actual_qty');
    }

    public function test_stale_version_is_rejected(): void
    {
        $id = $this->createSchedule($this->order('PO1', 100), $this->line(), '2026-10-01', '2026-10-03', 100)
            ->json('data.schedule.id');

        $this->putJson("/api/schedules/$id/days/2026-10-01/actual", ['actual_qty' => 30, 'version' => 99])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'stale');
    }

    public function test_schedule_with_actuals_cannot_be_deleted(): void
    {
        $id = $this->createSchedule($this->order('PO1', 100), $this->line(), '2026-10-01', '2026-10-03', 100)
            ->json('data.schedule.id');
        $this->putJson("/api/schedules/$id/days/2026-10-01/actual", ['actual_qty' => 33, 'version' => 1])->assertOk();

        $this->deleteJson("/api/schedules/$id")->assertStatus(423)->assertJsonPath('reason', 'locked');
    }
}
