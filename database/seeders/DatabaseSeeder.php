<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Demo account so the human can sign in and click through the shell.
        // Roles arrive in a later section; for now this is just an authenticated user.
        // Idempotent so re-running `db:seed` won't collide on the unique email.
        User::updateOrCreate(
            ['email' => 'admin@uprl.test'],
            [
                'name' => 'Demo Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
    }
}
