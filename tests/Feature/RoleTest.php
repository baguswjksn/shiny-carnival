<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    private function viewer(): User
    {
        return User::factory()->create(['role' => 'viewer']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_viewer_can_read_data(): void
    {
        $this->actingAs($this->viewer());

        $this->getJson('/api/schedules')->assertOk();
        $this->getJson('/api/lines')->assertOk();
        $this->getJson('/api/orders')->assertOk();
        $this->get('/schedules')->assertOk();
    }

    public function test_viewer_cannot_write(): void
    {
        $this->actingAs($this->viewer());
        $line = Line::create(['code' => 'L1', 'name' => 'Line 1']);

        $this->postJson('/api/lines', ['code' => 'L2', 'name' => 'Line 2'])
            ->assertForbidden()->assertJsonPath('reason', 'forbidden');
        $this->putJson("/api/lines/{$line->id}", ['code' => 'L1', 'name' => 'X'])->assertForbidden();
        $this->deleteJson("/api/lines/{$line->id}")->assertForbidden();
        $this->postJson('/api/schedules', [])->assertForbidden();
        $this->putJson('/api/schedules/1/days/2026-10-01/actual', ['actual_qty' => 1, 'version' => 1])->assertForbidden();

        $this->assertDatabaseCount('lines', 1);
    }

    public function test_admin_can_write(): void
    {
        $this->actingAs($this->admin());

        $this->postJson('/api/lines', ['code' => 'L1', 'name' => 'Line 1'])->assertCreated();
    }

    public function test_add_button_is_hidden_for_viewer_and_shown_for_admin(): void
    {
        $this->actingAs($this->viewer())->get('/schedules')->assertOk()->assertDontSee('id="add-btn"', false);
        $this->actingAs($this->admin())->get('/schedules')->assertOk()->assertSee('id="add-btn"', false);
    }

    public function test_role_cannot_be_mass_assigned(): void
    {
        $user = User::create(['name' => 'X', 'email' => 'x@example.test', 'password' => 'secret-pass-123', 'role' => 'admin']);

        $this->assertFalse($user->fresh()->isAdmin());
    }
}
