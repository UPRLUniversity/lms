<?php

namespace App\Models;

use App\Enums\CertificateLayout;
use App\Models\Concerns\HasMedia;
use Database\Factories\CertificateTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named certificate design: which of the two shipped layouts, its signatory block(s),
 * an optional accent-colour override and the show_grade toggle. Purely configuration —
 * issuing a certificate freezes a copy of this into Certificate::snapshot, so editing a
 * template here never rewrites an already-issued PDF's record.
 */
class CertificateTemplate extends Model
{
    /** @use HasFactory<CertificateTemplateFactory> */
    use HasFactory, HasMedia;

    protected $fillable = [
        'name',
        'is_default',
        'layout',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'layout' => CertificateLayout::class,
            'config' => 'array',
        ];
    }

    /**
     * @return HasMany<Course, $this>
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /**
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * @param  Builder<CertificateTemplate>  $query
     */
    public function scopeDefault(Builder $query): void
    {
        $query->where('is_default', true);
    }

    public function showGrade(): bool
    {
        return (bool) ($this->config['show_grade'] ?? false);
    }

    public function accentColor(): string
    {
        return $this->config['accent_color'] ?? $this->layout->defaultAccentColor();
    }

    /**
     * @return array{name: string, title: string, signature_media_id: int|null}|null
     */
    public function signatoryOne(): ?array
    {
        return $this->normalizeSignatory($this->config['signatory_one'] ?? null);
    }

    /**
     * @return array{name: string, title: string, signature_media_id: int|null}|null
     */
    public function signatoryTwo(): ?array
    {
        return $this->normalizeSignatory($this->config['signatory_two'] ?? null);
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
            'name' => $signatory['name'],
            'title' => $signatory['title'] ?? '',
            'signature_media_id' => $signatory['signature_media_id'] ?? null,
        ];
    }

    /**
     * The frozen slice of this template a Certificate snapshots at issuance — layout,
     * accent, signatories (with resolved image URLs, since the Media row itself could
     * later be replaced) and the show_grade toggle.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'certificate_template_id' => $this->id,
            'template_name' => $this->name,
            'layout' => $this->layout->value,
            'accent_color' => $this->accentColor(),
            'show_grade' => $this->showGrade(),
            'signatories' => collect([$this->signatoryOne(), $this->signatoryTwo()])
                ->filter()
                ->map(fn (array $s) => [
                    'name' => $s['name'],
                    'title' => $s['title'],
                    'signature_media_id' => $s['signature_media_id'],
                ])
                ->values()
                ->all(),
        ];
    }
}
