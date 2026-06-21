<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pooled-mode selection rules: "draw N questions from category C at difficulty D".
 * Each attempt resolves these rules into a frozen, randomised question set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_pool_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('question_categories')->cascadeOnDelete();
            $table->string('difficulty')->nullable(); // null = any difficulty
            $table->unsignedInteger('count')->default(1);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['assessment_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_pool_rules');
    }
};
