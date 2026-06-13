<?php

namespace App\Services\Media;

use App\Enums\MediaPurpose;
use App\Models\Media;
use App\Services\Media\Concerns\ValidatesMediaUploads;
use Cloudinary\Cloudinary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

/**
 * Public-image storage backed by Cloudinary (production). Cloudinary produces
 * responsive derivatives + WebP/AVIF + CDN delivery; we apply the per-purpose
 * transformation hints from config/media.php on upload. Only instantiated when
 * MEDIA_DRIVER=cloudinary (see MediaServiceProvider) — never in the test suite.
 */
class CloudinaryMediaService implements MediaUploadService
{
    use ValidatesMediaUploads;

    public function __construct(private Cloudinary $cloudinary) {}

    public function upload(UploadedFile $file, MediaPurpose $purpose, ?Model $owner = null, array $options = []): Media
    {
        $this->validateUpload($file, $purpose);

        $response = $this->cloudinary->uploadApi()->upload($file->getRealPath(), array_filter([
            'folder' => 'uprl/'.$purpose->value,
            'transformation' => $purpose->transformations() ?: null,
            'resource_type' => 'image',
        ]));

        $media = new Media([
            'purpose' => $purpose,
            'visibility' => $purpose->visibility(),
            'provider' => 'cloudinary',
            'disk' => $purpose->disk(),
            'path' => null,
            'public_id' => $response['public_id'] ?? null,
            'url' => $response['secure_url'] ?? null,
            'mime' => (string) $file->getMimeType(),
            'size_bytes' => (int) ($response['bytes'] ?? $file->getSize()),
            'width' => isset($response['width']) ? (int) $response['width'] : null,
            'height' => isset($response['height']) ? (int) $response['height'] : null,
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
        if ($media->public_id) {
            $this->cloudinary->uploadApi()->destroy($media->public_id);
        }

        $media->delete();
    }
}
