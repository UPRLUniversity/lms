<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();

            // Externally-visible ID (per CLAUDE.md: ULIDs for certificates) — used for the
            // gated download route. Never used for public verification (that's the serial).
            $table->ulid('public_id')->unique();

            // Human-readable, printed on the PDF and typed into /verify manually:
            // UPRL-{YEAR}-{6 random uppercase alphanumeric}.
            $table->string('serial')->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();

            // The template in effect as of the last (re-)issue — for admin reference only;
            // the frozen snapshot below is what's authoritative for what was printed.
            $table->foreignId('certificate_template_id')->nullable()->constrained()->nullOnDelete();

            // Immutable-until-reissue record of what was issued: student name, course
            // title, layout/accent/signatories, completion date and — per the Section 7
            // amendment — the grade as recorded in the Section 6.5 CourseGradeRecord at
            // that moment. Editing the live scale/template afterwards never touches this.
            $table->json('snapshot');

            $table->timestamp('issued_at');

            // Null until the queued render job finishes — the "pending" state the
            // completion screen polls for.
            $table->timestamp('rendered_at')->nullable();

            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason')->nullable();

            $table->timestamps();

            // One certificate per student per course — a re-completion never duplicates.
            $table->unique(['user_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
