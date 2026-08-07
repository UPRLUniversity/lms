<?php

namespace App\Support\Import;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * What a particular bulk import IS. Everything generic — reading the spreadsheet,
 * staging the upload, the preview screen, queueing past the row threshold, the
 * completion notification — lives in ImportRunner and is shared. A definition supplies
 * only what differs: the columns, how to judge a row, and how to write it.
 *
 * The contract is deliberately split into `prepare` (once per file) and
 * `inspect`/`apply` (once per row). That split is what keeps an import of 500 rows
 * from issuing 500 lookups: prepare resolves every email, code and title the file
 * mentions in a handful of queries and hands back a context the row methods read from.
 */
interface ImportDefinition
{
    /** Stable slug: the route parameter, the staging directory, the template filename. */
    public function key(): string;

    /** Screen title, e.g. "Import questions". */
    public function title(): string;

    /** One sentence on what this import does, shown under the title. */
    public function intro(): string;

    /**
     * @return array<int, ImportColumn>
     */
    public function columns(): array;

    /**
     * Illustrative rows for the downloadable template — real, valid-looking examples
     * beat an empty header, because most people fill in a template by overtyping it.
     *
     * @return array<int, array<string, string>>
     */
    public function sampleRows(): array;

    /**
     * May this user run this import, against this scope? Called before anything is read.
     */
    public function authorize(User $user, ?Model $scope): bool;

    /**
     * Resolve every lookup the file needs, in bulk, once. Whatever is returned is passed
     * back to inspect() and apply() untouched.
     *
     * The actor is passed EXPLICITLY rather than read from the session, because the
     * queued path has no session: a definition that asked Gate for the "current user"
     * would judge every row against a null user and refuse the lot. A definition whose
     * verdicts depend on who is importing should stash the actor in the context it
     * returns and check against that.
     *
     * @param  Collection<int, ImportRow>  $rows
     * @return array<string, mixed>
     */
    public function prepare(Collection $rows, ?Model $scope, User $actor): array;

    /**
     * Judge one row: call $row->fail(...) with a problem key, or leave it OK. Must not
     * write anything — inspection runs on every preview.
     *
     * @param  array<string, mixed>  $context
     */
    public function inspect(ImportRow $row, array $context, ?Model $scope): void;

    /**
     * Perform the write for one OK row. Return false to count it as skipped (a race
     * that inspection could not have seen). Throwing is also caught by the runner and
     * counted as skipped — a single bad row must never abort a 500-row import.
     *
     * @param  array<string, mixed>  $context
     */
    public function apply(ImportRow $row, array $context, ?Model $scope, User $actor): bool;

    /**
     * Human label for a problem key this definition can produce. Return null to fall
     * back to the runner's shared labels (empty row, missing required column…).
     */
    public function problemLabel(string $problem): ?string;

    /**
     * Where to send the human after the import finishes, and the noun to count in the
     * confirmation ("Imported 12 questions"). Singular form; pluralised by the runner.
     */
    public function noun(): string;

    /**
     * Route name for the screen this import returns to, plus its parameters.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function returnRoute(?Model $scope): array;
}
