<?php

namespace App\Services\Media;

use App\Enums\MediaPurpose;
use App\Models\Media;
use App\Services\Media\Concerns\ValidatesMediaUploads;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Storage for SENSITIVE files (submissions, certificates, lesson resources). Files
 * live on the private disk and are NEVER given a public URL — access is only via a
 * short-lived signed temporaryUrl() or the policy-gated download route.
 */
class PrivateFileService
{
    use ValidatesMediaUploads;

    public function store(UploadedFile $file, MediaPurpose $purpose, Model $owner): Media
    {
        $this->validateUpload($file, $purpose);

        $disk = $purpose->disk();
        $path = $file->store($purpose->value, $disk);

        $media = new Media([
            'purpose' => $purpose,
            'visibility' => $purpose->visibility(),
            'provider' => 'local',
            'disk' => $disk,
            'path' => $path,
            'public_id' => null,
            'url' => null, // sensitive: no public URL, ever
            'mime' => (string) $file->getMimeType(),
            'size_bytes' => (int) $file->getSize(),
            'width' => null,
            'height' => null,
            'original_name' => $file->getClientOriginalName(),
            'uploaded_by' => Auth::id(),
        ]);

        $media->mediable()->associate($owner);
        $media->save();

        return $media;
    }

    /**
     * A short-lived signed URL to the streaming route. Implemented with signed
     * routes (not a disk driver feature) so it works locally and under
     * Storage::fake without an S3-compatible backend.
     */
    public function temporaryUrl(Media $media, int $ttlMinutes = 15): string
    {
        return URL::temporarySignedRoute(
            'media.temporary',
            now()->addMinutes($ttlMinutes),
            ['media' => $media->getKey()],
        );
    }

    /**
     * Stream the file as a download. Callers (the controller) authorize via
     * MediaPolicy before invoking this.
     */
    public function download(Media $media): StreamedResponse
    {
        return Storage::disk($media->disk)->download($media->path, $media->original_name);
    }

    /**
     * Remove the underlying private file and its Media record. Used when a lesson's
     * file is replaced or the lesson is changed to a non-file type.
     */
    public function delete(Media $media): void
    {
        if ($media->path && Storage::disk($media->disk)->exists($media->path)) {
            Storage::disk($media->disk)->delete($media->path);
        }

        $media->delete();
    }
}
