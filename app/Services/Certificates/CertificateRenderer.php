<?php

namespace App\Services\Certificates;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Media;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Turns a Certificate (or, for the admin live preview, a bare CertificateTemplate +
 * sample data) into HTML/PDF. Every image — logo, sunburst watermark, signatures, QR —
 * is embedded as a base64 data URI before dompdf ever sees the markup: dompdf has no
 * GD/Imagick available in this environment, and QR/SVG assets render through dompdf's
 * own bundled SVG support, so nothing here depends on a missing extension. Config
 * inline, fonts web-safe (Georgia/Helvetica) — the same "no external dependency at
 * render time" reasoning as the Section 1 transactional e-mail theme.
 */
class CertificateRenderer
{
    /**
     * The finished PDF, as raw bytes.
     */
    public function renderPdf(Certificate $certificate): string
    {
        $layout = \App\Enums\CertificateLayout::from($certificate->snapshot['layout']);

        return Pdf::loadView($layout->view(), $this->dataForCertificate($certificate))
            ->setPaper('a4', 'landscape')
            ->output();
    }

    /**
     * Plain HTML for the admin live preview — the exact same view the PDF uses, so what
     * the admin sees is what gets printed (font substitution aside).
     */
    public function renderPreviewHtml(CertificateTemplate $template, bool $withGrade): string
    {
        return view($template->layout->view(), $this->dataForPreview($template, $withGrade))->render();
    }

    /**
     * @return array<string, mixed>
     */
    private function dataForCertificate(Certificate $certificate): array
    {
        $snapshot = $certificate->snapshot;

        return [
            'studentName' => $snapshot['student_name'],
            'courseTitle' => $snapshot['course_title'],
            'completionDate' => $snapshot['completion_date'],
            'serial' => $certificate->serial,
            'verifyUrl' => route('verify.show', $certificate->serial),
            'accentColor' => $snapshot['accent_color'],
            'signatories' => collect($snapshot['signatories'] ?? [])
                ->map(fn (array $s) => [
                    'name' => $s['name'],
                    'title' => $s['title'],
                    'signatureDataUri' => $this->embedMedia(
                        $s['signature_media_id'] ? Media::find($s['signature_media_id']) : null
                    ),
                ])
                ->all(),
            'gradeLine' => $certificate->gradeLine(),
            'logoDataUri' => $this->embedLogo(),
            'sunburstDataUri' => CertificateAsset::sunburstDataUri($snapshot['accent_color']),
            'motifDataUri' => $this->motif($snapshot['layout'], $snapshot['accent_color']),
            'qrDataUri' => CertificateAsset::qrDataUri(route('verify.show', $certificate->serial)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dataForPreview(CertificateTemplate $template, bool $withGrade): array
    {
        $accent = $template->accentColor();
        $sampleSerial = 'UPRL-'.now()->year.'-PREVIEW';

        return [
            'studentName' => 'Ada Lovelace',
            'courseTitle' => 'Foundations of Public Relations & Strategic Communication',
            'completionDate' => now()->isoFormat('D MMMM YYYY'),
            'serial' => $sampleSerial,
            'verifyUrl' => route('verify.show', $sampleSerial),
            'accentColor' => $accent,
            'signatories' => collect([$template->signatoryOne(), $template->signatoryTwo()])
                ->filter()
                ->map(fn (array $s) => [
                    'name' => $s['name'],
                    'title' => $s['title'],
                    'signatureDataUri' => $this->embedMedia(
                        $s['signature_media_id'] ? Media::find($s['signature_media_id']) : null
                    ),
                ])
                ->all(),
            'gradeLine' => $withGrade && $template->showGrade() ? 'A · 4.5/5.0' : null,
            'logoDataUri' => $this->embedLogo(),
            'sunburstDataUri' => CertificateAsset::sunburstDataUri($accent),
            'motifDataUri' => $this->motif($template->layout->value, $accent),
            'qrDataUri' => CertificateAsset::qrDataUri(route('verify.show', $sampleSerial)),
        ];
    }

    private function motif(string $layout, string $accent): string
    {
        return $layout === 'modern'
            ? CertificateAsset::diagonalMotifDataUri($accent)
            : CertificateAsset::sunburstDataUri($accent);
    }

    private function embedLogo(): ?string
    {
        $path = public_path(config('brand.logos.color'));

        if (! file_exists($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode(file_get_contents($path));
    }

    /**
     * Embed a Media row's bytes — from the local disk directly when it's a local
     * upload, otherwise fetched once from its (Cloudinary) URL. Never throws: a
     * missing/broken signature image just renders as a text-only signature line.
     */
    private function embedMedia(?Media $media): ?string
    {
        if ($media === null) {
            return null;
        }

        try {
            if ($media->provider === 'local' && $media->path && Storage::disk($media->disk)->exists($media->path)) {
                $contents = Storage::disk($media->disk)->get($media->path);
            } elseif ($media->url) {
                $contents = Http::timeout(5)->get($media->url)->throw()->body();
            } else {
                return null;
            }

            return 'data:'.$media->mime.';base64,'.base64_encode($contents);
        } catch (\Throwable) {
            return null;
        }
    }

}
