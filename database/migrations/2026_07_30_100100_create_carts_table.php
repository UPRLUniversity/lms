<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carts.
 *
 * A cart belongs either to a user or to a signed-out visitor identified by a cookie
 * token — never both, and exactly one is always set. Guests must be able to fill a
 * cart before registering (that is the whole point of the public catalogue), so
 * CartService::merge() folds the token cart into the user's on login.
 *
 * unit_price is snapshotted onto the item at add time so the cart shows a stable
 * figure, but it is NOT trusted at checkout: CheckoutService re-resolves every price
 * from PricingService before writing the order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('session_token', 64)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // One live cart per user, and one per guest token.
            $table->unique('user_id');
            $table->unique('session_token');
            $table->index('expires_at');
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->timestamps();

            // A course is in a cart once — quantity is meaningless for a course seat.
            $table->unique(['cart_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
