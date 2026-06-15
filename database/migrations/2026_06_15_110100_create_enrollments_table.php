<?php

use App\Enums\EnrollmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default(EnrollmentStatus::Active->value);
            $table->string('source');
            // When the student first joined (enrolled / requested / waitlisted). Drives
            // both display and the FIFO ordering of the waitlist.
            $table->timestamp('enrolled_at');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_note')->nullable();   // optional reject/approve note
            $table->timestamps();

            // A student holds at most one enrollment row per course — the DB-level
            // guarantee that a duplicate enrollment is impossible.
            $table->unique(['user_id', 'course_id']);

            // The two hot paths: a course's roster by status, and a student's courses.
            $table->index(['course_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
