<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Services\Media\PrivateFileService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function __construct(private PrivateFileService $files) {}

    /**
     * Policy-gated download for a logged-in, authorized user. Route applies
     * `auth` + `can:view,media`.
     */
    public function download(Media $media): StreamedResponse
    {
        abort_if($media->isPublic(), 404);

        return $this->files->download($media);
    }

    /**
     * Streams a private file for a valid temporaryUrl signature. The `signed`
     * middleware enforces the (time-limited) signature; possession authorizes.
     */
    public function temporary(Media $media): StreamedResponse
    {
        abort_if($media->isPublic(), 404);

        return $this->files->download($media);
    }
}
