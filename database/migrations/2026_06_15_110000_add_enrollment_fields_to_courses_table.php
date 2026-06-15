<?php

use App\Enums\EnrollmentMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            // How students enrol, and the size of the cohort (null = unlimited).
            $table->string('enrollment_mode')->default(EnrollmentMode::Open->value)->after('visibility');
            $table->unsignedInteger('capacity')->nullable()->after('enrollment_mode');

            // Optional enrolment window; null bounds mean "no limit on that side".
            $table->timestamp('enrollment_opens_at')->nullable()->after('capacity');
            $table->timestamp('enrollment_closes_at')->nullable()->after('enrollment_opens_at');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn([
                'enrollment_mode',
                'capacity',
                'enrollment_opens_at',
                'enrollment_closes_at',
            ]);
        });
    }
};
