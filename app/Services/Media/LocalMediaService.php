<?php

namespace App\Services\Media;

use App\Enums\MediaPurpose;
use App\Models\Media;
use App\Services\Media\Concerns\ValidatesMediaUploads;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Public-image storage backed by the local "public" disk. Used in dev and the test
 * suite so neither needs a Cloudinary account or network access.
 */
class LocalMediaService implements MediaUploadService
{
    use ValidatesMediaUploads;

    public function upload(UploadedFile $file, MediaPurpose $purpose, ?Model $owner = null, array $options = []): Media
    {
        $this->validateUpload($file, $purpose);

        $disk = $purpose->disk();
        [$width, $height] = $this->imageDimensions($file);

        // Hashed name on disk; original name kept in the DB.
        $path = $file->store($purpose->value, $disk);

        $media = new Media([
            'purpose' => $purpose,
            'visibility' => $purpose->visibility(),
            'provider' => 'local',
            'disk' => $disk,
            'path' => $path,
            'public_id' => null,
            'url' => Storage::disk($disk)->url($path),
            'mime' => (string) $file->getMimeType(),
            'size_bytes' => (int) $file->getSize(),
            'width' => $width,
            'height' => $height,
            'original_name' => $file->getClientOriginalName(),
            'uploaded_by' => $options['uploaded_by'] ?? Auth::id(),
        ]);

        if ($owner) {
            $media->mediable()->associate($owner);
        }

        $media->save();

        return $media;
    }

    public function destroy(Media $media): void
    {
        if ($media->path && Storage::disk($media->disk)->exists($media->path)) {
            Storage::disk($media->disk)->delete($media->path);
        }

        $media->delete();
    }
}
