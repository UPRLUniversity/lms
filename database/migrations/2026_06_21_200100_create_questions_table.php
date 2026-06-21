<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('question_categories')->nullOnDelete();
            // Scope: the course this question belongs to (null = global/personal bank).
            $table->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->string('difficulty')->default('medium');
            $table->longText('prompt');                 // rich HTML
            $table->longText('explanation')->nullable(); // rich HTML, shown post-submission
            $table->decimal('points', 6, 2)->default(1);
            // Per-type structured data: options, accepted answers, pairs, sub-questions.
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['course_id', 'type']);
            $table->index(['category_id', 'difficulty']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
