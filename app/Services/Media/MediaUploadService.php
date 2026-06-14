<?php

namespace App\Services\Media;

use App\Enums\MediaPurpose;
use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * Contract for PUBLIC image storage (avatars, covers, lesson/editor images,
 * signatures). Bound to LocalMediaService in dev/testing and CloudinaryMediaService
 * in production via MediaServiceProvider. All uploads MUST go through this — never
 * the storage/Cloudinary SDK directly in controllers or models.
 */
interface MediaUploadService
{
    /**
     * Validate (mime/size per purpose) and store a public file, returning the
     * persisted Media record.
     *
     * @param  array<string, mixed>  $options
     */
    public function upload(UploadedFile $file, MediaPurpose $purpose, ?Model $owner = null, array $options = []): Media;

    /**
     * Remove the underlying file and the Media record.
     */
    public function destroy(Media $media): void;
}
