<?php

namespace App\Models;

use App\Enums\MediaPurpose;
use App\Models\Concerns\HasMedia;
use Database\Factories\CertificateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student's certificate for one course — a single row per (user, course), never
 * duplicated by a re-completion. `snapshot` freezes everything the PDF was rendered
 * from (template config + the Section 6.5 grade, as issued); it changes only when an
 * admin explicitly re-issues, never as a side effect of editing a template/scale
 * afterwards. The PDF itself renders asynchronously (see GenerateCertificatePdf) —
 * `rendered_at` is null while it's pending.
 */
class Certificate extends Model
{
    /** @use HasFactory<CertificateFactory> */
    use HasFactory, HasMedia;

    protected $fillable = [
        'public_id',
        'serial',
        'user_id',
        'course_id',
        'certificate_template_id',
        'snapshot',
        'issued_at',
        'rendered_at',
        'revoked_at',
        'revocation_reason',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'issued_at' => 'datetime',
            'rendered_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<CertificateTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class, 'certificate_template_id');
    }

    /**
     * @param  Builder<Certificate>  $query
     */
    public function scopeRevoked(Builder $query): void
    {
        $query->whereNotNull('revoked_at');
    }

    /**
     * @param  Builder<Certificate>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Whether the queued PDF render has completed — false shows the brief pending
     * state on the completion screen / My Certificates card.
     */
    public function isReady(): bool
    {
        return $this->rendered_at !== null;
    }

    public function pdf(): ?Media
    {
        return $this->firstMediaFor(MediaPurpose::Certificates);
    }

    /**
     * The grade line as frozen at issuance, e.g. "A · 4.5/5.0" — or null when the
     * template's show_grade was off, or the course had no gradable items.
     */
    public function gradeLine(): ?string
    {
        $grade = $this->snapshot['grade'] ?? null;

        if ($grade === null) {
            return null;
        }

        $mode = $grade['display_mode'] ?? 'both';
        $parts = [];

        if ($mode !== 'points') {
            $parts[] = $grade['grade_label'];
        }

        if ($mode !== 'letter') {
            $point = number_format((float) $grade['grade_point'], 1);
            $limit = number_format((float) ($grade['scale_limit'] ?? 0), 1);
            $parts[] = ($grade['show_scale_limit'] ?? true)
                ? $point.($grade['separator'] ?? '/').$limit
                : $point;
        }

        return implode(' · ', $parts);
    }
}
