<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment methods — the admin-managed switchboard over the drivers declared in
 * config/commerce.php.
 *
 * Credentials live here rather than in env because staff rotate them, not deploys:
 * an admin pastes a new Paystack secret key and switches Test → Live from the
 * Payment methods screen without touching the server. `config` is cast
 * encrypted:array on the model, so secrets are never at rest in plaintext and never
 * appear in a database dump or a query log.
 *
 * A row existing means "this app knows how to talk to that gateway". Only
 * is_enabled decides whether it is offered at checkout.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();            // matches a config/commerce.php driver key
            $table->string('label');
            $table->boolean('is_enabled')->default(false);
            $table->string('environment')->default('test');   // test | live
            $table->text('config')->nullable();          // encrypted JSON: keys, account details
            $table->longText('instructions')->nullable(); // rich text, shown for offline methods
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['is_enabled', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
