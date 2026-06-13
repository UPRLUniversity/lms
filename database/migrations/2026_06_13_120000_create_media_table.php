<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();

            // Polymorphic owner (nullable so media can exist before being attached,
            // e.g. in-editor uploads created before the parent record is saved).
            $table->nullableMorphs('mediable');

            $table->string('purpose');                 // MediaPurpose value
            $table->string('visibility')->default('private');
            $table->string('provider');                // cloudinary | local
            $table->string('disk');                    // filesystem disk

            $table->string('path')->nullable();        // key on the disk (private/local)
            $table->string('public_id')->nullable();   // Cloudinary public id
            $table->text('url')->nullable();           // public URL (public media only)

            $table->string('mime');
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('original_name');

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('purpose');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
