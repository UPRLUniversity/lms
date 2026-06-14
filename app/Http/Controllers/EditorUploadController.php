<?php

namespace App\Http\Controllers;

use App\Enums\MediaPurpose;
use App\Services\Media\MediaUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives in-editor image uploads from TinyMCE, routes them through the canonical
 * MediaUploadService (never base64-inlined into content), and returns the
 * { location: url } shape TinyMCE expects. Auth-gated.
 */
class EditorUploadController extends Controller
{
    public function __construct(private MediaUploadService $media) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'image', 'max:4096'],
        ]);

        $media = $this->media->upload($request->file('file'), MediaPurpose::EditorUploads);

        return response()->json(['location' => $media->url]);
    }
}
