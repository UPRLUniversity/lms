<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            // Idempotency flag for the 15-minute "new pending enrollment" instructor
            // digest: set the moment a pending row has been folded into a digest, so
            // the same request is never reported twice across command runs.
            $table->timestamp('pending_digested_at')->nullable()->after('decision_note');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn('pending_digested_at');
        });
    }
};
