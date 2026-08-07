<?php

namespace App\Support\Import;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The generic half of every bulk import: read the file, judge every row, stage it so
 * the confirm step needn't re-upload, then write the OK rows and report exactly what
 * happened. Definitions supply the domain; this supplies the machinery.
 *
 * Two properties are load-bearing and worth stating plainly:
 *
 *  - **Preview never writes.** analyse() is pure, so an admin can upload, look, and
 *    walk away having changed nothing.
 *  - **Confirm re-analyses.** The staged file is re-read and re-judged at import time
 *    rather than trusting the preview, because minutes may have passed and someone else
 *    may have created the very user this file is about to create.
 */
class ImportRunner
{
    /** Above this many rows the write runs on the queue rather than in the request. */
    public const QUEUE_THRESHOLD = 100;

    private const STAGING_DIR = 'imports';

    public function __construct(private readonly SpreadsheetReader $reader) {}

    /**
     * Read + judge, writing nothing.
     *
     * @return array{rows: Collection<int, ImportRow>, counts: array<string, int>, truncated: bool}
     *
     * @throws ImportFormatException
     */
    public function analyse(ImportDefinition $definition, string $path, ?Model $scope, User $actor): array
    {
        $rows = $this->reader->read($path, $definition->columns());

        if ($rows->isEmpty()) {
            throw new ImportFormatException('That file has a header but no data rows.');
        }

        $context = $definition->prepare($rows, $scope, $actor);

        foreach ($rows as $row) {
            $definition->inspect($row, $context, $scope);
        }

        return [
            'rows' => $rows,
            'counts' => $this->counts($rows),
            'truncated' => $rows->count() >= SpreadsheetReader::MAX_ROWS,
        ];
    }

    /**
     * Re-analyse the staged file and write every OK row.
     *
     * @return array{imported: int, skipped: int, total: int}
     */
    public function import(ImportDefinition $definition, string $path, ?Model $scope, User $actor): array
    {
        $report = $this->analyse($definition, $path, $scope, $actor);
        $context = $definition->prepare($report['rows'], $scope, $actor);

        $imported = 0;
        $skipped = 0;

        foreach ($report['rows'] as $row) {
            if (! $row->isOk()) {
                $skipped++;

                continue;
            }

            try {
                $definition->apply($row, $context, $scope, $actor)
                    ? $imported++
                    : $skipped++;
            } catch (\Throwable $e) {
                // One malformed row must never take the other 499 with it.
                report($e);
                $skipped++;
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'total' => $report['counts']['total'],
        ];
    }

    /**
     * Stash the upload so the confirm step can re-read it without a second upload.
     * Returns the token the preview screen carries.
     */
    public function stage(ImportDefinition $definition, string $contents, string $extension): string
    {
        $token = (string) Str::uuid();

        Storage::disk('local')->put($this->pathFor($definition, $token, $extension), $contents);

        return $token.'.'.$extension;
    }

    /**
     * Absolute path of a staged file, or null when it has expired or the token is not
     * a bare "uuid.ext" — the token comes from a form field, so it must never be able
     * to name a path outside the staging directory.
     */
    public function staged(ImportDefinition $definition, string $token): ?string
    {
        if (! preg_match('/^[0-9a-f-]{36}\.(csv|xlsx|xls)$/i', $token)) {
            return null;
        }

        $relative = self::STAGING_DIR.'/'.$definition->key().'/'.$token;

        if (! Storage::disk('local')->exists($relative)) {
            return null;
        }

        return Storage::disk('local')->path($relative);
    }

    public function discard(ImportDefinition $definition, string $token): void
    {
        if (preg_match('/^[0-9a-f-]{36}\.(csv|xlsx|xls)$/i', $token)) {
            Storage::disk('local')->delete(self::STAGING_DIR.'/'.$definition->key().'/'.$token);
        }
    }

    /** Relative path used by the queued job, which has no request to read from. */
    public function relativePath(ImportDefinition $definition, string $token): string
    {
        return self::STAGING_DIR.'/'.$definition->key().'/'.$token;
    }

    public function template(ImportDefinition $definition): string
    {
        return $this->reader->template($definition->columns(), $definition->sampleRows());
    }

    /**
     * Human label for a row's verdict — the definition's own wording where it has one,
     * otherwise the shared vocabulary every import shares.
     */
    public function label(ImportDefinition $definition, string $problem): string
    {
        return $definition->problemLabel($problem) ?? match ($problem) {
            ImportRow::OK => 'Ready to import',
            ImportRow::EMPTY_ROW => 'Empty row',
            default => 'Problem',
        };
    }

    /**
     * @param  Collection<int, ImportRow>  $rows
     * @return array<string, int>
     */
    private function counts(Collection $rows): array
    {
        $valid = $rows->filter(fn (ImportRow $r) => $r->isOk())->count();

        return [
            'total' => $rows->count(),
            'valid' => $valid,
            'invalid' => $rows->count() - $valid,
        ];
    }

    private function pathFor(ImportDefinition $definition, string $token, string $extension): string
    {
        return self::STAGING_DIR.'/'.$definition->key().'/'.$token.'.'.$extension;
    }
}
