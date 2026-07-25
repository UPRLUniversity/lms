<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A queued report export (Section 10). Tracks a large report while it builds in the
 * background and points at the finished file on the private disk, which is streamed only
 * through a Policy-gated download route — never a public URL (constitution: generated
 * reports are sensitive files).
 */
class GeneratedReport extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'report',
        'format',
        'title',
        'filename',
        'disk',
        'path',
        'row_count',
        'filters',
        'status',
        'ready_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'row_count' => 'integer',
            'status' => ReportStatus::class,
            'ready_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isReady(): bool
    {
        return $this->status === ReportStatus::Ready && $this->path !== null;
    }

    /**
     * Whether the stored file still exists on its disk (guards the download route against
     * a swept/rotated file).
     */
    public function fileExists(): bool
    {
        return $this->path !== null && Storage::disk($this->disk)->exists($this->path);
    }
}
