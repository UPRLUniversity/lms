<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The student-facing side: "My Certificates" and the gated download/status endpoints.
 * Also reused by the admin registry's "re-download" (CertificatePolicy::view allows
 * either the owner or certificates.view).
 */
class CertificateController extends Controller
{
    public function mine(Request $request): View
    {
        $certificates = Certificate::query()
            ->where('user_id', $request->user()->id)
            ->with(['course:id,title,slug', 'template:id,name'])
            ->orderByDesc('issued_at')
            ->get();

        return view('certificates.mine', ['certificates' => $certificates]);
    }

    public function download(Certificate $certificate): StreamedResponse
    {
        $this->authorize('view', $certificate);

        $media = $certificate->pdf();
        abort_if($media === null, 404, 'The certificate is still being prepared — try again shortly.');

        return \Illuminate\Support\Facades\Storage::disk($media->disk)->download($media->path, $certificate->serial.'.pdf');
    }

    /**
     * Polled by the completion screen while the queued render is in flight.
     */
    public function status(Certificate $certificate): JsonResponse
    {
        $this->authorize('view', $certificate);

        return response()->json(['ready' => $certificate->isReady()]);
    }
}
