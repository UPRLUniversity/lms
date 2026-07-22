<?php

namespace App\Models;

use App\Casts\RichHtml;
use App\Enums\MediaPurpose;
use App\Enums\SubmissionStatus;
use App\Models\Concerns\HasMedia;
use Database\Factories\SubmissionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One immutable version of a student's hand-in: the typed body and/or uploaded files,
 * when it arrived and whether it was late. Content is never edited after submission —
 * a resubmission is a NEW row with version n+1. Only `status` (and the return-note
 * audit fields) ever change, driven by grading / return-for-resubmission.
 */
class Submission extends Model
{
    /** @use HasFactory<SubmissionFactory> */
    use HasFactory, HasMedia;

    protected $fillable = [
        'assignment_id',
        'user_id',
        'version',
        'body',
        'submitted_at',
        'is_late',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'body' => RichHtml::class.':basic',
            'version' => 'integer',
            'submitted_at' => 'datetime',
            'is_late' => 'boolean',
            'status' => SubmissionStatus::class,
            'returned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    /**
     * @return HasOne<Grade, $this>
     */
    public function grade(): HasOne
    {
        return $this->hasOne(Grade::class);
    }

    /**
     * The uploaded files of this version.
     *
     * @return Collection<int, Media>
     */
    public function files(): Collection
    {
        return $this->mediaFor(MediaPurpose::Submissions);
    }

    public function isGraded(): bool
    {
        return $this->status === SubmissionStatus::Graded;
    }

    public function isReturned(): bool
    {
        return $this->status === SubmissionStatus::ReturnedForResubmission;
    }

    public function awaitingGrading(): bool
    {
        return $this->status === SubmissionStatus::Submitted;
    }
}
