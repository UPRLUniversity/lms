<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            // Server-authoritative deadline: started_at + time_limit. Null = untimed.
            $table->timestamp('expires_at')->nullable();

            $table->decimal('score', 8, 2)->nullable();      // points earned
            $table->decimal('max_score', 8, 2)->nullable();  // points possible (frozen)
            $table->unsignedTinyInteger('percentage')->nullable();
            $table->boolean('passed')->nullable();
            $table->string('status')->default('in_progress');

            // The frozen layout for this attempt: ordered question ids + per-question
            // option/pair order + (for pooled) the drawn selection. The single source of
            // truth a submission is validated against — a refresh never reshuffles.
            $table->json('layout')->nullable();

            $table->timestamps();

            $table->unique(['assessment_id', 'user_id', 'attempt_number']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempts');
    }
};
