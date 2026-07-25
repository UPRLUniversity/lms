<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Queued report exports (Section 10). A report over ~2k rows is built in the background
 * and its file stored on the private disk; this row tracks who asked for it, what it is,
 * and where the finished file lives — the basis of the "your report is ready" in-app
 * notification + gated download link. Small exports stream straight from the request and
 * never touch this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_reports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('report');          // registry key: learner|instructor|compliance|certification
            $table->string('format', 8);       // xlsx|csv|pdf
            $table->string('title');           // human title echoed on the notification
            $table->string('filename');        // download filename shown to the user
            $table->string('disk')->default('private');
            $table->string('path')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->json('filters')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('ready_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_reports');
    }
};
