<?php

namespace App\Jobs;

use App\Enums\MediaPurpose;
use App\Models\Certificate;
use App\Services\Certificates\CertificateRenderer;
use App\Services\Media\PrivateFileService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Renders a Certificate's PDF and stores it via PrivateFileService, then stamps
 * rendered_at — the signal the completion screen / My Certificates card polls for.
 * Queued so a slow render never blocks the request that completed the course. Safe to
 * run twice for the same certificate (a re-issue re-dispatches it): any previous PDF
 * media is cleared by the caller (CertificateService) before this job is queued.
 */
class GenerateCertificatePdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $certificateId) {}

    public function handle(CertificateRenderer $renderer, PrivateFileService $files): void
    {
        $certificate = Certificate::find($this->certificateId);

        if ($certificate === null) {
            return;
        }

        $pdf = $renderer->renderPdf($certificate);

        $files->storeGenerated(
            $pdf,
            $certificate->serial.'.pdf',
            MediaPurpose::Certificates,
            $certificate,
            'application/pdf',
        );

        $certificate->update(['rendered_at' => now()]);
    }
}
