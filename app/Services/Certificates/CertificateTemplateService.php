<?php

namespace App\Services\Certificates;

use App\Models\CertificateTemplate;
use App\Models\Media;
use App\Services\Media\MediaUploadService;
use Illuminate\Support\Facades\DB;

/**
 * Persists a certificate template and keeps "exactly one default" true by construction
 * — same invariant, same escape hatch (unchecking your only default is a no-op) as
 * GradeScaleService::save(). Signature images are uploaded ahead of time (AJAX, see
 * CertificateTemplateController::uploadSignature) and only referenced here by Media id;
 * this is where they're attached to the template (and any replaced image is cleaned up).
 */
class CertificateTemplateService
{
    public function __construct(private readonly MediaUploadService $media) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(CertificateTemplate $template, array $data): CertificateTemplate
    {
        return DB::transaction(function () use ($template, $data) {
            $wantsDefault = (bool) ($data['is_default'] ?? false);

            if (! $wantsDefault && $template->exists && $template->is_default
                && ! CertificateTemplate::query()->default()->where('id', '!=', $template->id)->exists()) {
                $wantsDefault = true;
            }

            $oldConfig = $template->exists ? $template->config : null;

            $config = [
                'signatory_one' => $this->normalizeSignatory($data['signatory_one'] ?? null),
                'signatory_two' => $this->normalizeSignatory($data['signatory_two'] ?? null),
                'accent_color' => ($data['accent_color'] ?? '') !== '' ? $data['accent_color'] : null,
                'show_grade' => (bool) ($data['show_grade'] ?? false),
            ];

            $template->fill([
                'name' => $data['name'],
                'layout' => $data['layout'],
                'is_default' => $wantsDefault,
                'config' => $config,
            ]);
            $template->save();

            if ($wantsDefault) {
                CertificateTemplate::query()->where('id', '!=', $template->id)->update(['is_default' => false]);
            }

            $this->syncSignatureMedia($template, $oldConfig, $config);

            return $template->refresh();
        });
    }

    /**
     * @param  array<string, mixed>|null  $signatory
     * @return array{name: string, title: string, signature_media_id: int|null}|null
     */
    private function normalizeSignatory(?array $signatory): ?array
    {
        if ($signatory === null || trim((string) ($signatory['name'] ?? '')) === '') {
            return null;
        }

        return [
            'name' => trim($signatory['name']),
            'title' => trim((string) ($signatory['title'] ?? '')),
            'signature_media_id' => ($signatory['signature_media_id'] ?? '') !== ''
                ? (int) $signatory['signature_media_id']
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $oldConfig
     * @param  array<string, mixed>  $newConfig
     */
    private function syncSignatureMedia(CertificateTemplate $template, ?array $oldConfig, array $newConfig): void
    {
        foreach (['signatory_one', 'signatory_two'] as $slot) {
            $newId = $newConfig[$slot]['signature_media_id'] ?? null;
            $oldId = $oldConfig[$slot]['signature_media_id'] ?? null;

            if ($newId !== null && $newId !== $oldId) {
                $media = Media::find($newId);
                if ($media !== null) {
                    $template->attachMedia($media);
                }
            }

            if ($oldId !== null && $oldId !== $newId) {
                $orphan = Media::find($oldId);
                if ($orphan !== null && $orphan->mediable_type === CertificateTemplate::class && $orphan->mediable_id === $template->id) {
                    $this->media->destroy($orphan);
                }
            }
        }
    }
}
