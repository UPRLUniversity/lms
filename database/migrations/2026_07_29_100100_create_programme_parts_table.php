<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The stages within a programme — "Part I", "Part II", "Part 3". A student works
 * through a part's courses, and the part is the unit the published curriculum
 * states a credit total against.
 *
 * credit_target records the total the printed curriculum STATES, which is not
 * always the sum of the listed course credits: CPR Part I is published as
 * "Total 24" while its eleven courses sum to 28, because the stated figure counts
 * compulsory + required-elective and excludes the two 2-credit pure electives.
 * Storing the stated target lets the UI show it beside the computed sum rather
 * than silently picking one and looking wrong against the prospectus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programme_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->constrained()->cascadeOnDelete();
            $table->string('name');                    // "Part I"
            $table->string('slug');
            $table->string('description')->nullable();
            $table->unsignedInteger('credit_target')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            // Slugs are unique per programme, not globally — every programme has a
            // "part-i", and they address as /programmes/cpr#part-i.
            $table->unique(['programme_id', 'slug']);
            $table->index(['programme_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_parts');
    }
};
