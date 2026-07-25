<?php

namespace App\Models;

use App\Casts\RichHtml;
use Database\Factories\CourseAnnouncementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An instructor-authored update posted to a course's enrolled students. Notifies
 * every actively-enrolled student per their preferences and lists on the course's
 * Announcements tab (both the builder, for the author, and the learner-facing page).
 */
class CourseAnnouncement extends Model
{
    /** @use HasFactory<CourseAnnouncementFactory> */
    use HasFactory;

    protected $fillable = [
        'course_id',
        'user_id',
        'title',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'body' => RichHtml::class,
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
