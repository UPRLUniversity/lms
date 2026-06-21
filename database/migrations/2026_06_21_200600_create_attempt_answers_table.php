<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();

            // The student's raw response, shape depends on question type:
            //   mcq_single/true_false → option id; mcq_multi → [option ids];
            //   fill_blank → string; matching → {leftId: rightId}; essay → string;
            //   scenario → {subQuestionId: <one of the above>}.
            $table->json('response')->nullable();

            $table->boolean('is_correct')->nullable();        // null until graded (essays)
            $table->decimal('points_awarded', 8, 2)->nullable();
            $table->decimal('points_possible', 8, 2)->default(0);
            $table->longText('feedback')->nullable();          // grader's written feedback (rich)
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->boolean('flagged')->default(false);        // student flagged for review
            $table->timestamps();

            $table->unique(['attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_answers');
    }
};
