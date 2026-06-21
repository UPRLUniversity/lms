<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            // Set for pre/post placements; null for a standalone course-level assessment.
            $table->foreignId('module_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->string('slug');
            $table->longText('instructions')->nullable(); // rich HTML
            $table->string('placement')->default('standalone');
            $table->string('status')->default('draft');
            $table->string('selection_mode')->default('fixed');

            // Settings.
            $table->unsignedTinyInteger('passing_score')->default(70); // percentage
            $table->unsignedInteger('max_attempts')->nullable();        // null = unlimited
            $table->unsignedInteger('time_limit_minutes')->nullable();  // null = untimed
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();
            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('shuffle_options')->default(false);
            $table->string('review_policy')->default('immediately');
            $table->boolean('show_explanations')->default(true);
            $table->boolean('is_required')->default(true); // counts toward course progress

            $table->unsignedInteger('position')->default(0)->index();
            $table->timestamps();

            $table->unique(['course_id', 'slug']);
            $table->index(['module_id', 'placement']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
