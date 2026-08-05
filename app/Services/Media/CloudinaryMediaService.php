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
            'folder' => $this->folderFor($purpose),
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

    /**
     * "<root>/<purpose>" — e.g. uprl/course_covers. The root is configurable so a
     * staging site can share the Cloudinary account without mixing its uploads into
     * the live library; the purpose segment means a new MediaPurpose files itself.
     */
    private function folderFor(MediaPurpose $purpose): string
    {
        $root = trim((string) config('media.root_folder', 'uprl'), '/');

        return ($root === '' ? '' : $root.'/').$purpose->value;
    }
}
