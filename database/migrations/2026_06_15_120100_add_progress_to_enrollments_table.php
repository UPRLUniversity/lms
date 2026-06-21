<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            // Cached course-completion percentage (0–100), recalculated on every lesson
            // completion event so list pages never have to derive it per row.
            $table->unsignedTinyInteger('progress_percent')->default(0)->after('source');
            // When the course hit 100% — drives the history "completed" column and the
            // congratulations moment. Distinct from enrolled_at.
            $table->timestamp('completed_at')->nullable()->after('progress_percent');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn(['progress_percent', 'completed_at']);
        });
    }
};
