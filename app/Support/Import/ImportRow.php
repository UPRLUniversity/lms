<?php

namespace App\Support\Import;

/**
 * One row of an uploaded spreadsheet as it travels through the import: raw cells keyed
 * by column, the line number it came from (so the preview can point the human at the
 * right line in THEIR file), and — after inspection — a verdict.
 *
 * A row carries at most one problem. When several apply, the definition reports the one
 * the human has to fix first; a preview listing every fault of every row is noise.
 */
class ImportRow
{
    public const OK = 'ok';

    public const EMPTY_ROW = 'empty';

    /** Verdict, set by the definition during inspection. */
    public string $problem = self::OK;

    /** Extra detail for the preview: resolved names, a reason, whatever helps. */
    public array $resolved = [];

    /**
     * @param  int  $line  1-indexed line in the uploaded file, header included
     * @param  array<string, string>  $cells  trimmed values keyed by column key
     */
    public function __construct(
        public readonly int $line,
        public readonly array $cells,
    ) {}

    public function get(string $key, string $default = ''): string
    {
        return $this->cells[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return ($this->cells[$key] ?? '') !== '';
    }

    public function isOk(): bool
    {
        return $this->problem === self::OK;
    }

    /**
     * Flag a problem and optionally attach context for the preview. The FIRST problem
     * flagged wins — inspection runs cheapest-and-most-fundamental check first, so the
     * message a human sees is the one that has to be fixed before anything else matters.
     */
    public function fail(string $problem, array $resolved = []): void
    {
        if ($this->problem === self::OK) {
            $this->problem = $problem;
        }

        $this->resolved = array_merge($this->resolved, $resolved);
    }

    public function resolve(array $resolved): void
    {
        $this->resolved = array_merge($this->resolved, $resolved);
    }

    /** True when every cell is blank — a trailing line in a spreadsheet, not an error. */
    public function isBlank(): bool
    {
        foreach ($this->cells as $value) {
            if (trim($value) !== '') {
                return false;
            }
        }

        return true;
    }
}
