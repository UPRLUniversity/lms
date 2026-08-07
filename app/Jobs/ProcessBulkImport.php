<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\BulkImportCompletedNotification;
use App\Support\Import\ImportFormatException;
use App\Support\Import\ImportRegistry;
use App\Support\Import\ImportRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Runs a large import off the request. Takes the definition's KEY and the scope's class
 * and id rather than the objects themselves: a definition is a service (not
 * serialisable), and re-resolving the scope means the job sees the current state of the
 * course or assignment rather than a snapshot taken minutes earlier.
 */
class ProcessBulkImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** A big import can legitimately take a while; don't let the queue kill it early. */
    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(
        public string $definitionKey,
        public string $path,
        public ?string $scopeClass,
        public int|string|null $scopeId,
        public int $actorId,
    ) {}

    public function handle(ImportRegistry $registry, ImportRunner $runner): void
    {
        if (! Storage::disk('local')->exists($this->path)) {
            return;
        }

        $actor = User::find($this->actorId);

        if (! $actor) {
            Storage::disk('local')->delete($this->path);

            return;
        }

        $definition = $registry->get($this->definitionKey);
        $scope = $this->scope();

        // The scope was deleted between queueing and running — nothing to import into.
        if ($this->scopeClass && ! $scope) {
            Storage::disk('local')->delete($this->path);

            return;
        }

        try {
            // The actor travels with the job rather than being read from a session the
            // queue does not have — see ImportDefinition::prepare.
            $result = $runner->import(
                $definition,
                Storage::disk('local')->path($this->path),
                $scope,
                $actor,
            );
        } catch (ImportFormatException $e) {
            Log::warning('Bulk import could not be read', [
                'import' => $this->definitionKey,
                'actor_id' => $this->actorId,
                'reason' => $e->getMessage(),
            ]);

            Storage::disk('local')->delete($this->path);

            return;
        }

        Log::info('Bulk import completed', [
            'import' => $this->definitionKey,
            'actor_id' => $this->actorId,
        ] + $result);

        $actor->notify(new BulkImportCompletedNotification($result, $definition->noun()));

        Storage::disk('local')->delete($this->path);
    }

    private function scope(): ?\Illuminate\Database\Eloquent\Model
    {
        if (! $this->scopeClass || ! class_exists($this->scopeClass)) {
            return null;
        }

        return $this->scopeClass::find($this->scopeId);
    }
}
