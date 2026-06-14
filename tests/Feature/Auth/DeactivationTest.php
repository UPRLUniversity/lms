<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeactivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivated_user_is_rejected_at_login_with_a_clear_message(): void
    {
        $user = User::factory()->inactive()->create();

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'deactivated',
            session('errors')->first('email'),
        );
    }

    public function test_active_user_deactivated_mid_session_is_booted_on_next_request(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')->assertOk();

        // Admin flips the switch while the user still holds a session.
        $user->update(['is_active' => false]);

        $this->actingAs($user)->get('/profile')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
