<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Course pricing.
 *
 * A course's price is normally INHERITED from its primary programme's per-paper fee
 * (see Section 11 and PricingService) — the published schedule prices by tier, not
 * per course, so storing a copy on every course would be 40 numbers to keep in sync
 * instead of 3. These two columns are the exceptions to that rule:
 *
 *   is_free        overrides everything; the course costs nothing.
 *   price_override a specific price for this course, ignoring its programme's fee.
 *
 * is_free defaults to TRUE so every course that already exists — and every course
 * created by an existing factory or test — keeps behaving exactly as it did before
 * commerce existed. Charging is opt-in, which is the safe direction for a default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->decimal('price_override', 10, 2)->nullable()->after('level');
            $table->boolean('is_free')->default(true)->after('price_override');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['price_override', 'is_free']);
        });
    }
};
