<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_user_sees_the_shell(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Welcome back, Ada');     // greeting renders
        $response->assertSee('Dashboard');             // page title in topbar
        $response->assertSee('Continue learning');     // shell content
        $response->assertSee(config('brand.motto'));   // sidebar motto
    }
}
