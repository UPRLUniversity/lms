<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Section 1 — identity layer. Profile fields, the activation flag that gates
 * login, learning-preferences JSON, and login auditing columns. Append-only:
 * the original users migration (an accepted section) is never edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Profile.
            $table->string('phone')->nullable()->after('email');
            $table->string('title')->nullable()->after('phone');   // e.g. "Senior Lecturer"
            $table->text('bio')->nullable()->after('title');

            // Learning preferences (email digest opt-in lives here for now).
            $table->json('learning_preferences')->nullable()->after('bio');

            // Activation — deactivated users cannot log in (no hard deletes).
            $table->boolean('is_active')->default(true)->after('learning_preferences');

            // Login auditing.
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'title',
                'bio',
                'learning_preferences',
                'is_active',
                'last_login_at',
                'last_login_ip',
            ]);
        });
    }
};
