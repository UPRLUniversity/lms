<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a student was admitted PAST the progression gate, and on whose authority.
 *
 * Staff must be able to admit an exception — a transfer student, prior credit earned
 * elsewhere, a registrar's judgement call. What they must not be able to do is admit one
 * invisibly. Both columns stay null for the overwhelming majority of enrolments; they are
 * written only when the gate would actually have refused, so a non-null row always means
 * something worth asking about rather than noise from every purchase that ever cleared.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreignId('prerequisite_override_by')
                ->nullable()
                ->after('approved_by')
                ->constrained('users')
                ->nullOnDelete();

            $table->string('prerequisite_override_reason', 500)
                ->nullable()
                ->after('prerequisite_override_by');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prerequisite_override_by');
            $table->dropColumn('prerequisite_override_reason');
        });
    }
};
