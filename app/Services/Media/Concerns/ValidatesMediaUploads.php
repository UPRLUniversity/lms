<?php

namespace App\Services\Media\Concerns;

use App\Enums\MediaPurpose;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Server-side validation shared by every storage service, so no upload path can
 * bypass the per-purpose mime/size allow-list.
 */
trait ValidatesMediaUploads
{
    protected function validateUpload(UploadedFile $file, MediaPurpose $purpose): void
    {
        $mime = (string) $file->getMimeType();
        $allowed = $purpose->allowedMimes();

        if ($allowed !== [] && ! in_array($mime, $allowed, true)) {
            throw ValidationException::withMessages([
                'file' => "Files of type \"{$mime}\" are not allowed for {$purpose->value}.",
            ]);
        }

        $maxKb = $purpose->maxKb();

        if ($maxKb > 0 && $file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages([
                'file' => "The file exceeds the {$maxKb}KB limit for {$purpose->value}.",
            ]);
        }
    }

    /**
     * Read [width, height] for image uploads; [null, null] otherwise.
     *
     * @return array{0: int|null, 1: int|null}
     */
    protected function imageDimensions(UploadedFile $file): array
    {
        if (! str_starts_with((string) $file->getMimeType(), 'image/')) {
            return [null, null];
        }

        $info = @getimagesize($file->getRealPath());

        return $info ? [(int) $info[0], (int) $info[1]] : [null, null];
    }
}
