<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programmes — the professional qualifications a course is packaged and examined
 * under (CPR, DPR, Professional Variant, Master Class).
 *
 * This is a SECOND axis alongside Faculty → Department, not a replacement.
 * Faculty/Department answers "who owns and teaches this course"; Programme/Part
 * answers "how is it packaged, examined and charged for". A single course can sit
 * in several programmes at once, which is why the join is a pivot (see the
 * course_programme_part migration) rather than a column on courses.
 *
 * The fee columns live here because the published NIPR schedule prices by
 * programme tier, never per course: a course's default price is its programme's
 * per_paper_fee, and registration/administration are one-off entry fees a student
 * pays once per programme. Section 12 reads them; nothing else does yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programmes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();          // CPR, DPR, NPV, NMC
            $table->string('slug')->unique();
            $table->string('tagline')->nullable();     // one line for the programme card
            $table->longText('description')->nullable();

            // Fees, in the currency configured for the install. Decimal, never float —
            // money must not accumulate binary rounding error.
            $table->decimal('registration_fee', 10, 2)->default(0);
            $table->decimal('administration_fee', 10, 2)->default(0);
            $table->decimal('per_paper_fee', 10, 2)->default(0);

            $table->unsignedInteger('position')->default(0)->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programmes');
    }
};
