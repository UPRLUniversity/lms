<?php

namespace App\Http\Controllers\Courses;

use App\Enums\LessonType;
use App\Enums\MediaPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Courses\StoreLessonRequest;
use App\Http\Requests\Courses\UpdateLessonRequest;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Services\Courses\VideoEmbedService;
use App\Services\Media\PrivateFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Per-type lesson authoring inside the builder, all over AJAX. Video lessons accept
 * either a pasted YouTube/Vimeo URL (parsed + validated by VideoEmbedService) or an
 * uploaded file; file-type lessons store privately via PrivateFileService. Switching
 * a lesson's type cleans up the payload it no longer uses.
 */
class LessonController extends Controller
{
    public function __construct(
        private VideoEmbedService $video,
        private PrivateFileService $files,
    ) {}

    /**
     * Lesson data for populating the slide-over editor.
     */
    public function show(Course $course, Lesson $lesson): JsonResponse
    {
        $this->authorize('view', $course);
        $this->assertBelongs($course, $lesson);

        $file = $lesson->file();

        return response()->json([
            'id' => $lesson->id,
            'module_id' => $lesson->module_id,
            'title' => $lesson->title,
            'type' => $lesson->type->value,
            'content_text' => $lesson->content_text,
            'video_url' => $lesson->video_url,
            'video_source' => $lesson->video_provider === 'upload' ? 'upload' : 'embed',
            'external_url' => $lesson->external_url,
            'duration_minutes' => $lesson->duration_minutes,
            'is_free_preview' => $lesson->is_free_preview,
            'file' => $file ? [
                'name' => $file->original_name,
                'size' => $file->size_bytes,
            ] : null,
        ]);
    }

    public function store(StoreLessonRequest $request, Course $course, Module $module): JsonResponse
    {
        abort_unless($module->course_id === $course->id, 404);

        $lesson = $module->lessons()->create([
            'title' => $request->validated('title'),
            'type' => $request->validated('type'),
            'position' => (int) $module->lessons()->max('position') + 1,
        ]);

        $this->applyPayload($lesson, $request);

        return response()->json([
            'ok' => true,
            'message' => 'Lesson added.',
            'lesson_id' => $lesson->id,
        ]);
    }

    public function update(UpdateLessonRequest $request, Course $course, Lesson $lesson): JsonResponse
    {
        $this->assertBelongs($course, $lesson);

        $lesson->update([
            'title' => $request->validated('title'),
            'type' => $request->validated('type'),
        ]);

        $this->applyPayload($lesson, $request);

        return response()->json(['ok' => true, 'message' => 'Lesson saved.']);
    }

    public function destroy(Course $course, Lesson $lesson): JsonResponse
    {
        $this->authorize('manageCurriculum', $course);
        $this->assertBelongs($course, $lesson);

        if ($file = $lesson->file()) {
            $this->files->delete($file);
        }

        $lesson->delete();

        return response()->json(['ok' => true, 'message' => 'Lesson removed.']);
    }

    /**
     * Write the type-specific payload, clearing fields the new type doesn't use and
     * removing any now-orphaned uploaded file.
     */
    private function applyPayload(Lesson $lesson, Request $request): void
    {
        $type = $lesson->type;

        $attributes = [
            'duration_minutes' => $request->input('duration_minutes') ?: null,
            'is_free_preview' => $request->boolean('is_free_preview'),
            // Reset every payload column; the matching branch below repopulates.
            'content_text' => null,
            'video_url' => null,
            'video_provider' => null,
            'external_url' => null,
        ];

        $usesFile = $type->isFileUpload()
            || ($type === LessonType::Video && $request->input('video_source') === 'upload');

        match (true) {
            $type === LessonType::Text => $attributes['content_text'] = $request->input('content_text'),
            $type === LessonType::ExternalLink => $attributes['external_url'] = $request->input('external_url'),
            $type === LessonType::Video && ! $usesFile => $this->fillEmbed($attributes, (string) $request->input('video_url')),
            $type === LessonType::Video && $usesFile => $attributes['video_provider'] = 'upload',
            default => null,
        };

        $lesson->update($attributes);

        // A file was uploaded: replace any existing one. Otherwise, if the lesson no
        // longer uses a file, drop the orphan.
        if ($request->hasFile('file')) {
            if ($existing = $lesson->file()) {
                $this->files->delete($existing);
            }
            $this->files->store($request->file('file'), MediaPurpose::LessonMedia, $lesson);
        } elseif (! $usesFile && ($existing = $lesson->file())) {
            $this->files->delete($existing);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function fillEmbed(array &$attributes, string $url): void
    {
        $attributes['video_url'] = $url;
        $attributes['video_provider'] = $this->video->provider($url);
    }

    private function assertBelongs(Course $course, Lesson $lesson): void
    {
        abort_unless($lesson->module->course_id === $course->id, 404);
    }
}
