<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_grade_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();

            // Insert-only versioning (mirrors submissions): a row is immutable once
            // written; an admin "recompute" supersedes it with a new version rather
            // than editing it in place.
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('superseded_at')->nullable();

            $table->decimal('final_percent', 5, 2);
            $table->string('grade_label');
            $table->decimal('grade_point', 4, 2);
            // The whole scale (id, name, limit, display settings, bands) as applied at
            // computation time, so a later scale edit never rewrites a recorded grade.
            $table->json('scale_snapshot');
            $table->timestamp('computed_at');
            $table->timestamps();

            $table->unique(['user_id', 'course_id', 'version']);
            $table->index(['course_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_grade_records');
    }
};
