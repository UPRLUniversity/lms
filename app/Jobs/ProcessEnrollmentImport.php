<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Courses\BulkEnrollmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Performs a large enrolment import (>100 rows) off the request, so the upload returns
 * immediately. Reads the staged CSV from the private 'local' disk, runs the same
 * BulkEnrollmentService used synchronously, then removes the staged file.
 */
class ProcessEnrollmentImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $path,
        public int $actorId,
    ) {}

    public function handle(BulkEnrollmentService $service): void
    {
        if (! Storage::disk('local')->exists($this->path)) {
            return;
        }

        $actor = User::find($this->actorId);
        if (! $actor) {
            Storage::disk('local')->delete($this->path);

            return;
        }

        $content = Storage::disk('local')->get($this->path);
        $result = $service->import($content, $actor);

        Log::info('Bulk enrolment import completed', [
            'actor_id' => $this->actorId,
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
            'total' => $result['total'],
        ]);

        Storage::disk('local')->delete($this->path);
    }
}
