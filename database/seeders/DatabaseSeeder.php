<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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
        // NB: the User model casts `password` as 'hashed', so pass the PLAIN value —
        // the cast hashes it once. Calling Hash::make() here would double-hash it.
        User::updateOrCreate(
            ['email' => 'admin@uprl.test'],
            [
                'name' => 'Demo Admin',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );
    }
}
