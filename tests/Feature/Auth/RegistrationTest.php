<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register_as_a_student(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Event::fake();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole(Role::Student->value));
        $this->assertFalse($user->hasRole(Role::Admin->value));

        // Registered event drives the queued verification e-mail.
        Event::assertDispatched(Registered::class);
    }

    public function test_registered_user_starts_unverified_and_is_gated(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'gated@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'gated@example.com')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());

        // The app (verified-gated dashboard) bounces them to the notice screen.
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('verification.notice'));
    }
}
