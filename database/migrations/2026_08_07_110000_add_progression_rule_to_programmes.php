<?php

use App\Enums\ProgressionRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part-level progression: whether a programme's parts must be worked through in order.
 *
 * **Defaults to `open`, deliberately.** Merging this changes nothing until a human
 * switches a programme on. A migration that silently locked out live students — students
 * who are mid-way through a Part II course they were legitimately sold — would be the
 * worst possible way to ship this. `php artisan progression:audit` exists so the switch
 * is made with evidence rather than hope.
 *
 * `unlock_credits` overrides `credit_target` for the progression bar only, for the case
 * where the registrar wants a bar different from the total the prospectus publishes.
 * Null (the norm) means "use the published target"; the published figure stays the single
 * source of truth until somebody deliberately says otherwise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programmes', function (Blueprint $table) {
            $table->string('progression_rule')
                ->default(ProgressionRule::Open->value)
                ->after('is_active');
        });

        Schema::table('programme_parts', function (Blueprint $table) {
            $table->unsignedInteger('unlock_credits')->nullable()->after('credit_target');
        });
    }

    public function down(): void
    {
        Schema::table('programmes', function (Blueprint $table) {
            $table->dropColumn('progression_rule');
        });

        Schema::table('programme_parts', function (Blueprint $table) {
            $table->dropColumn('unlock_credits');
        });
    }
};
