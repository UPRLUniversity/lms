<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The idempotency flag for the "due in 48h" scheduled command: one row per
        // (assignment, user) the instant a reminder is sent, so a command that runs
        // hourly (to catch the 48h window precisely) never reminds the same student
        // about the same assignment twice.
        Schema::create('assignment_due_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('sent_at');

            $table->unique(['assignment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_due_reminders');
    }
};
