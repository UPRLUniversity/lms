<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a band is a PASS. Until now a grade scale could say what a mark is called and
 * what it is worth, but not whether the student passed — so "must pass CPR112" was not
 * expressible anywhere in the system, only "must complete" it.
 *
 * The backfill is a derivation of the existing data, not a guess about it: both seeded
 * scales (5-point A–F and 4-point A–F) put the fail band, and ONLY the fail band, at
 * grade_point 0.00, so `grade_point > 0` reproduces the current intent exactly for every
 * scale in the system.
 *
 * It is a column rather than a computed `grade_point > 0` because the two can legitimately
 * come apart: an institution may award 0.5 for a near-miss that is still a fail. Left
 * computed, that scale would silently start passing people. The column lets the registrar
 * say so.
 *
 * NOT NULL with **no default**, deliberately: every band must make the choice rather than
 * inherit one. A new band created without stating it is an error, not a pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Added nullable, backfilled, then tightened — a NOT NULL column with no default
        // cannot be added to a table that already has rows in one step.
        Schema::table('grade_bands', function (Blueprint $table) {
            $table->boolean('is_pass')->nullable()->after('grade_point');
        });

        DB::table('grade_bands')->where('grade_point', '>', 0)->update(['is_pass' => true]);
        DB::table('grade_bands')->where('grade_point', '<=', 0)->update(['is_pass' => false]);

        Schema::table('grade_bands', function (Blueprint $table) {
            $table->boolean('is_pass')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('grade_bands', function (Blueprint $table) {
            $table->dropColumn('is_pass');
        });
    }
};
