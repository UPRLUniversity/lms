<?php

use App\Enums\LessonProgressStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default(LessonProgressStatus::NotStarted->value);
            $table->timestamp('completed_at')->nullable();
            // Cumulative engaged time and the last video position (seconds), for resume.
            $table->unsignedInteger('seconds_spent')->default(0);
            $table->unsignedInteger('last_position_seconds')->default(0);
            $table->timestamps();

            // One progress row per (student, lesson) — the DB guarantee that a double
            // submit can never double-count: completion is a state, not an increment.
            $table->unique(['user_id', 'lesson_id']);

            // The hot path: a student's progress across a whole course's lessons.
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_progress');
    }
};
