<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            // One grade per submission version (regrading updates it in place).
            $table->foreignId('submission_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('grader_id')->constrained('users')->cascadeOnDelete();

            // Snapshot of the rubric selections as graded — [{criterion_id, criterion_title,
            // level_index, level_label, points}] — so the student's breakdown stays exactly
            // as graded even if the rubric is edited later. Null for rubric-free grades.
            $table->json('criterion_scores')->nullable();

            // Server-recomputed total; the client's live total is display-only.
            $table->decimal('points_total', 8, 2);
            $table->longText('feedback')->nullable();
            $table->timestamp('graded_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
