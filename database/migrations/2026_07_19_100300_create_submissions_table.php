<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // 1..n per (assignment, user). Prior versions are immutable: resubmission
            // inserts version n+1, it never updates an existing row.
            $table->unsignedInteger('version');

            $table->longText('body')->nullable(); // typed rich-text answer
            $table->timestamp('submitted_at');
            $table->boolean('is_late')->default(false);
            $table->string('status')->default('submitted'); // submitted | graded | returned_for_resubmission

            // Return-for-resubmission audit trail (the required note).
            $table->text('return_note')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('returned_at')->nullable();

            $table->timestamps();

            $table->unique(['assignment_id', 'user_id', 'version']);
            $table->index(['assignment_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
