<?php

namespace App\Console\Commands;

use App\Support\Security\RouteGuardAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * `php artisan audit:routes` — the route-permission map from the Section 15 hardening
 * sweep, printed on demand rather than only asserted in a test, so the answer to "what
 * protects this endpoint?" is one command away during a review.
 *
 * Exits non-zero when any mutating route is unguarded, which makes it usable as a CI gate.
 */
class AuditRoutesCommand extends Command
{
    protected $signature = 'audit:routes
        {--unguarded : Show only mutating routes with no guard}
        {--csv= : Write the full map to a CSV file instead of the terminal}';

    protected $description = 'Map every route to the permission, policy or stated reason that guards it';

    public function handle(RouteGuardAudit $audit): int
    {
        $map = $audit->map();
        $unguarded = $audit->unguardedMutating();

        if ($path = $this->option('csv')) {
            $this->writeCsv($map, $path);
            $this->info("Route map written to {$path}");

            return $unguarded->isEmpty() ? self::SUCCESS : self::FAILURE;
        }

        $rows = $this->option('unguarded') ? $unguarded : $map;

        $this->table(
            ['Method', 'URI', 'Guard', 'Action'],
            $rows->map(fn (array $row) => [
                $row['method'],
                Str::limit($row['uri'], 46),
                $this->guardLabel($row),
                Str::limit($row['action'], 44),
            ])->all(),
        );

        $mutating = $map->where('mutating', true)->count();

        $this->newLine();
        $this->line(sprintf(
            '%d routes · %d mutating · %d unguarded mutating',
            $map->count(),
            $mutating,
            $unguarded->count(),
        ));

        if ($unguarded->isNotEmpty()) {
            $this->newLine();
            $this->error('Unguarded mutating routes found. Every one must gain a guard, or an explicit reason in RouteGuardAudit::PUBLIC_BY_DESIGN.');

            foreach ($unguarded as $row) {
                $this->line("  {$row['method']} /{$row['uri']}  →  {$row['action']}");
            }

            return self::FAILURE;
        }

        $this->info('No unguarded mutating routes.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function guardLabel(array $row): string
    {
        if ($row['guards'] !== []) {
            return implode(', ', array_map(
                fn (string $g) => class_basename($g),
                $row['guards'],
            ));
        }

        if ($row['policy']) {
            return 'policy';
        }

        if ($row['public_reason'] !== null) {
            return 'public (by design)';
        }

        return $row['mutating'] ? 'NONE' : 'read-only';
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $map
     */
    private function writeCsv($map, string $path): void
    {
        $handle = fopen($path, 'wb');

        fputcsv($handle, ['Method', 'URI', 'Name', 'Action', 'Mutating', 'Guards', 'Policy', 'Public reason', 'Guarded']);

        foreach ($map as $row) {
            fputcsv($handle, [
                $row['method'],
                $row['uri'],
                $row['name'],
                $row['action'],
                $row['mutating'] ? 'yes' : 'no',
                implode(' ', $row['guards']),
                $row['policy'] ? 'yes' : 'no',
                $row['public_reason'] ?? '',
                $row['guarded'] ? 'yes' : 'no',
            ]);
        }

        fclose($handle);
    }
}
