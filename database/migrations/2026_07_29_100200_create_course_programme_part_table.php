<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Course ↔ programme part placements.
 *
 * This has to be many-to-many, and the published curriculum is the proof: CPR 112
 * (Principles of Public Relations) and CPR 115 (PR Media and Methods) each appear
 * in BOTH "CPR Part I" and "Professional Variant Part 1". A courses.part_id column
 * cannot express that.
 *
 * credit_load and requirement live on the pivot rather than on courses for the same
 * reason — the same course can carry a different weight and a different
 * compulsory/elective status depending on which programme is looking at it.
 *
 * is_primary marks the placement that decides the course's inherited price when a
 * course sits in programmes of different tiers (a CPR paper is ₦7,000, a Professional
 * one ₦15,000 — without a primary, a dual-placed course has no single answer).
 * At most one row per course may set it; enforced in the application layer
 * (Course::syncProgrammePlacements) because a partial unique index is not portable
 * across sqlite/mysql/pgsql, and this project's test suite runs on sqlite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_programme_part', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('programme_part_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('credit_load')->nullable();
            $table->string('requirement')->nullable();   // CourseRequirement enum
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            // A course appears at most once in any given part.
            $table->unique(['course_id', 'programme_part_id']);
            $table->index(['programme_part_id', 'position']);
            $table->index(['course_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_programme_part');
    }
};
