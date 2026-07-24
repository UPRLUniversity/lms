<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            // Per-course override; null = system default scale. Changing this affects
            // display and future completion snapshots only, never existing records.
            $table->foreignId('grade_scale_id')->nullable()->after('progression_mode')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grade_scale_id');
        });
    }
};
