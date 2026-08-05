<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Runtime settings (Section 15).
 *
 * Sparse by design: a row exists only for a setting an administrator has actually
 * changed. Everything else resolves to the default declared in config/settings.php,
 * so a fresh install has an empty table and behaves identically to a configured one.
 *
 * `value` is text rather than a typed column set — the type belongs to the schema in
 * config/settings.php, which is the single source of truth for what a setting IS, and
 * SettingsRepository casts on read. `group` is denormalised from that schema purely
 * so the table is legible when read directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
