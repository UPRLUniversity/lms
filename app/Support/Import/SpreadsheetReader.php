<?php

namespace App\Support\Import;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Turns an uploaded .xlsx/.xls/.csv into ImportRows keyed by column.
 *
 * Columns are matched BY HEADER NAME, not by position. That matters more than it
 * sounds: people reorder columns, and a positional reader silently imports emails into
 * the name field when they do. Matching is case- and separator-insensitive
 * ("Course code", "course_code", "COURSE-CODE" all resolve), because a template that
 * has been through Excel and a human rarely comes back byte-identical.
 */
class SpreadsheetReader
{
    /** Guard against a runaway file taking the request (and memory) with it. */
    public const MAX_ROWS = 5000;

    /**
     * @param  array<int, ImportColumn>  $columns
     * @return Collection<int, ImportRow>
     *
     * @throws ImportFormatException when the header is missing a required column
     */
    public function read(string $path, array $columns): Collection
    {
        $table = $this->toArray($path);

        if ($table === []) {
            throw new ImportFormatException('That file appears to be empty.');
        }

        $header = array_shift($table);
        $map = $this->mapHeader($header, $columns);

        $missing = [];
        foreach ($columns as $column) {
            if ($column->required && ! isset($map[$column->key])) {
                $missing[] = $column->label;
            }
        }

        if ($missing !== []) {
            throw new ImportFormatException(
                'The file is missing a required column: '.implode(', ', $missing)
                .'. Download the template to see the expected headings.'
            );
        }

        $rows = collect();

        foreach ($table as $index => $cells) {
            // +2: the header was shifted off, and spreadsheet lines are 1-indexed, so
            // the number shown in the preview matches the row number in their file.
            $row = new ImportRow($index + 2, $this->keyCells($cells, $map, $columns));

            if ($row->isBlank()) {
                continue;
            }

            $rows->push($row);

            if ($rows->count() >= self::MAX_ROWS) {
                break;
            }
        }

        return $rows->values();
    }

    /**
     * The template body for a definition — header plus its sample rows, as CSV. CSV
     * rather than xlsx deliberately: it opens in Excel, Sheets, Numbers and a text
     * editor alike, and the reader accepts whichever of those the human saves back.
     *
     * @param  array<int, ImportColumn>  $columns
     * @param  array<int, array<string, string>>  $samples
     */
    public function template(array $columns, array $samples): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, array_map(fn (ImportColumn $c) => $c->key, $columns));

        foreach ($samples as $sample) {
            fputcsv($handle, array_map(fn (ImportColumn $c) => $sample[$c->key] ?? '', $columns));
        }

        rewind($handle);
        $body = (string) stream_get_contents($handle);
        fclose($handle);

        // A BOM so Excel opens UTF-8 templates without mangling accented names.
        return "\xEF\xBB\xBF".$body;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function toArray(string $path): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $sheet = $reader->load($path)->getActiveSheet();
        } catch (\Throwable $e) {
            throw new ImportFormatException(
                'That file could not be read. Please upload a .csv or .xlsx spreadsheet.',
                previous: $e,
            );
        }

        $rows = [];

        foreach ($sheet->toArray(null, true, false, false) as $cells) {
            $rows[] = array_map(fn ($cell) => $this->stringify($cell), $cells);
        }

        return $rows;
    }

    /**
     * Excel hands back DateTime objects and floats for things a human typed as text.
     * A score of "70" must not arrive as "70.0", and a date must not arrive as 45678.
     */
    private function stringify(mixed $cell): string
    {
        // Only null is "no cell". Boolean false must NOT be treated as empty: a
        // spreadsheet reader casts the literal text "false" to a bool, and a true/false
        // question sheet is full of exactly that — blanking it here silently emptied
        // the answer column of every false answer.
        if ($cell === null) {
            return '';
        }

        if (is_bool($cell)) {
            return $cell ? 'true' : 'false';
        }

        if ($cell instanceof \DateTimeInterface) {
            return $cell->format('Y-m-d');
        }

        if (is_float($cell)) {
            // Whole floats are almost always integers that went through a spreadsheet.
            return floor($cell) === $cell && abs($cell) < PHP_INT_MAX
                ? (string) (int) $cell
                : (string) $cell;
        }

        return trim((string) $cell);
    }

    /**
     * Header label → column index, matched loosely.
     *
     * @param  array<int, string>  $header
     * @param  array<int, ImportColumn>  $columns
     * @return array<string, int>
     */
    private function mapHeader(array $header, array $columns): array
    {
        $normalised = [];
        foreach ($header as $index => $label) {
            $key = $this->normalise($label);
            if ($key !== '' && ! isset($normalised[$key])) {
                $normalised[$key] = $index;
            }
        }

        $map = [];
        foreach ($columns as $column) {
            foreach ([$column->key, $column->label] as $candidate) {
                $key = $this->normalise($candidate);
                if (isset($normalised[$key])) {
                    $map[$column->key] = $normalised[$key];

                    break;
                }
            }
        }

        return $map;
    }

    private function normalise(string $value): string
    {
        // Strip a leading BOM, lowercase, and reduce every separator to nothing so
        // "Course code", "course_code" and "COURSE-CODE" all land on "coursecode".
        $value = str_replace("\xEF\xBB\xBF", '', $value);

        return preg_replace('/[^a-z0-9]/', '', Str::lower(trim($value))) ?? '';
    }

    /**
     * @param  array<int, string>  $cells
     * @param  array<string, int>  $map
     * @param  array<int, ImportColumn>  $columns
     * @return array<string, string>
     */
    private function keyCells(array $cells, array $map, array $columns): array
    {
        $keyed = [];

        foreach ($columns as $column) {
            $index = $map[$column->key] ?? null;
            $keyed[$column->key] = $index !== null ? trim((string) ($cells[$index] ?? '')) : '';
        }

        return $keyed;
    }
}
