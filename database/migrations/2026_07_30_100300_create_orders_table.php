<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders and their line items.
 *
 * `reference` is a ULID shown to the buyer and handed to the gateway — the primary
 * key is never exposed, per the project convention for externally-visible ids.
 *
 * Every line item SNAPSHOTS its title and price. A course renaming or repricing next
 * term must not rewrite what a student was charged last term, so the order is a
 * record of the transaction, not a set of live lookups. course_id/programme_id stay
 * as nullable links for reporting, but the money and the wording come from the row.
 *
 * `kind` distinguishes a course seat from the one-off programme entry fees
 * (registration + administration) that the published schedule charges once per
 * programme rather than per paper.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('reference')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');

            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount_total', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('currency', 3)->default('NGN');

            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('coupon_code')->nullable();   // snapshot: the coupon may be deleted later

            $table->string('payment_method_key')->nullable();
            $table->string('gateway_reference')->nullable();
            $table->json('gateway_payload')->nullable();
            $table->json('billing')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('admin_note')->nullable();      // e.g. why a refund was recorded
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('gateway_reference');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('kind')->default('course');   // course | registration_fee | administration_fee

            // Nullable + nullOnDelete: deleting a course must never delete the record
            // of it having been sold.
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('programme_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');                     // snapshot
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('line_total', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['order_id', 'kind']);
            $table->index('course_id');
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->timestamps();

            // One redemption row per coupon per order, so a replayed payment webhook
            // cannot inflate the usage count. Enforced by the database rather than by
            // application care.
            $table->unique(['coupon_id', 'order_id']);
            $table->index(['coupon_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
