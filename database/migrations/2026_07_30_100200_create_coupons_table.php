<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coupons.
 *
 * Scope decides what a coupon may discount: the whole cart (global), one course, or
 * every course in a programme. An instructor may create coupons for their own
 * courses (course-scoped, gated by CoursePolicy); global and programme scopes are
 * admin-only, because they cost the institution money across many courses.
 *
 * The redemption ledger lives in its own later migration — it points at orders,
 * which do not exist until 100300.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();          // always stored upper-case
            $table->string('name')->nullable();
            $table->string('type');                     // percentage | fixed | full
            $table->decimal('value', 10, 2)->default(0); // percent for percentage, amount for fixed, ignored for full
            $table->string('scope')->default('global'); // global | course | programme
            $table->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('programme_id')->nullable()->constrained()->cascadeOnDelete();

            $table->unsignedInteger('max_redemptions')->nullable();  // null = unlimited
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'expires_at']);
            $table->index(['scope', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
