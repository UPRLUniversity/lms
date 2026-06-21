<?php

use App\Enums\ProgressionMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            // free → learners roam the curriculum freely; sequential → each lesson
            // unlocks only once the previous one is completed.
            $table->string('progression_mode')
                ->default(ProgressionMode::Free->value)
                ->after('enrollment_mode');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('progression_mode');
        });
    }
};
