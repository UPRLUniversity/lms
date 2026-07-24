<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_scales', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('scale_limit', 4, 2)->default(5.00);
            $table->boolean('is_default')->default(false);

            // Display settings — presentation only, never affects computation.
            $table->string('display_mode')->default('both'); // letter | points | both
            $table->boolean('show_scale_limit')->default(true);
            $table->string('separator', 10)->default('/');

            $table->string('status')->default('active'); // active | archived
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_scales');
    }
};
