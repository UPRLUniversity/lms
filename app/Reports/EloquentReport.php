<?php

namespace App\Reports;

use App\Reports\Contracts\Report;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * A report whose rows come straight from an Eloquent query. Subclasses supply the base
 * query and a chunk-mapper; count/paginate/export are derived once here so every
 * query-backed report paginates and exports identically. `mapChunk` (not a per-row map)
 * is the extension point so a subclass can batch-load auxiliary data (grade snapshots,
 * certificates) for a whole page/chunk and stay N+1-free.
 */
abstract class EloquentReport implements Report
{
    /**
     * The base record query for the given filters (before pagination/mapping).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    abstract protected function baseQuery(array $filters): Builder;

    /**
     * Map a loaded chunk of records to positional row arrays aligned with headings().
     *
     * @param  EloquentCollection<int, \Illuminate\Database\Eloquent\Model>  $records
     * @return array<int, array<int, scalar|null>>
     */
    abstract protected function mapChunk(EloquentCollection $records): array;

    public function count(array $filters): int
    {
        return $this->baseQuery($filters)->toBase()->getCountForPagination();
    }

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator $paginator */
        $paginator = $this->baseQuery($filters)->paginate($perPage)->withQueryString();

        $rows = $this->mapChunk($paginator->getCollection());
        $paginator->setCollection(new Collection($rows));

        return $paginator;
    }

    public function rows(array $filters): array
    {
        $rows = [];

        $this->baseQuery($filters)->chunk(500, function (EloquentCollection $records) use (&$rows): void {
            foreach ($this->mapChunk($records) as $row) {
                $rows[] = $row;
            }
        });

        return $rows;
    }

    public function summary(array $filters): array
    {
        return [];
    }

    /**
     * Cast a value for a cell, mapping "no value" to a tidy empty string (never 0, never
     * an em-dash) per the section's blank-cell rule.
     */
    protected function cell(mixed $value): string
    {
        return $value === null || $value === '' ? '' : (string) $value;
    }
}
