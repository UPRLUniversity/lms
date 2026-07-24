<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_scale_id')->constrained()->cascadeOnDelete();

            $table->string('label');
            $table->decimal('grade_point', 4, 2);
            $table->unsignedTinyInteger('min_percent');
            $table->unsignedTinyInteger('max_percent');
            // Badge colour token: success | gold | crimson | ink | neutral.
            $table->string('color')->default('neutral');
            // 0 = the top band, ascending — rebuilt wholesale on every scale save.
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['grade_scale_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_bands');
    }
};
