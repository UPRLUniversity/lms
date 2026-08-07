<?php

namespace App\Support\Import;

/**
 * One column of an import spreadsheet. Drives three things from a single declaration:
 * the downloadable template's header, the "what goes in this file" guidance on the
 * upload screen, and how a row's cells are keyed once parsed.
 */
class ImportColumn
{
    /**
     * @param  string  $key  the array key a parsed row uses (and the template header)
     * @param  string  $label  human column name shown in the guidance table
     * @param  bool  $required  whether a blank cell makes the row unimportable
     * @param  string|null  $hint  what the human needs to know to fill it in correctly
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly bool $required = false,
        public readonly ?string $hint = null,
    ) {}

    public static function make(string $key, string $label, bool $required = false, ?string $hint = null): self
    {
        return new self($key, $label, $required, $hint);
    }

    public static function required(string $key, string $label, ?string $hint = null): self
    {
        return new self($key, $label, true, $hint);
    }
}
