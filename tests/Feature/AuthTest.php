<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/schedules')->assertRedirect('/login');
    }

    public function test_api_returns_401_json_for_guest(): void
    {
        $this->getJson('/api/schedules')->assertUnauthorized();
    }

    public function test_user_can_log_in_and_reach_schedules(): void
    {
        User::factory()->create(['email' => 'admin@example.test', 'password' => 'secret-pass']);

        $this->post('/login', ['email' => 'admin@example.test', 'password' => 'secret-pass'])
            ->assertRedirect(route('schedules.index'));

        $this->assertAuthenticated();
        $this->get('/schedules')->assertOk();
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'admin@example.test', 'password' => 'secret-pass']);

        $this->post('/login', ['email' => 'admin@example.test', 'password' => 'nope'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_can_log_out(): void
    {
        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }
}
