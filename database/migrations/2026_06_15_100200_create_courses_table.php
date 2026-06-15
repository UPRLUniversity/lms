<?php

use App\Enums\CourseStatus;
use App\Enums\CourseVisibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('code')->unique();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('level');
            $table->string('summary', 500)->nullable();
            $table->longText('description')->nullable();      // sanitized rich HTML
            $table->json('learning_objectives')->nullable();  // repeatable rows
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('status')->default(CourseStatus::Draft->value)->index();
            $table->string('visibility')->default(CourseVisibility::PublicCatalogue->value);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->text('review_note')->nullable();          // admin's return-to-draft note
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
