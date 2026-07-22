<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            // Curriculum attachment point: after a module's lessons, or null = standalone
            // (end of the course outline), mirroring assessments.
            $table->foreignId('module_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            $table->string('title');
            $table->string('slug');
            $table->longText('instructions')->nullable();
            $table->string('type')->default('either'); // file | text | either

            $table->timestamp('due_at')->nullable();
            $table->boolean('allow_late')->default(false);

            // Gradebook denominator (Section 6.5) — publish is blocked unless > 0.
            $table->decimal('max_points', 8, 2)->nullable();
            $table->foreignId('rubric_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status')->default('draft'); // draft | published
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['course_id', 'slug']);
            $table->index(['course_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
