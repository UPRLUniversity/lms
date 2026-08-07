<?php

namespace App\Support\Import;

use Illuminate\Contracts\Container\Container;

/**
 * Resolves an import slug from the URL to its definition. One registration point, so
 * adding an importer is a single line here plus the definition class — no new
 * controller, no new routes, no new views.
 */
class ImportRegistry
{
    /** @var array<string, class-string<ImportDefinition>> */
    private array $definitions = [];

    public function __construct(private readonly Container $container) {}

    /**
     * @param  class-string<ImportDefinition>  $definition
     */
    public function register(string $key, string $definition): void
    {
        $this->definitions[$key] = $definition;
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    public function get(string $key): ImportDefinition
    {
        abort_unless($this->has($key), 404);

        return $this->container->make($this->definitions[$key]);
    }

    /**
     * @return array<int, ImportDefinition>
     */
    public function all(): array
    {
        return array_map(fn (string $class) => $this->container->make($class), array_values($this->definitions));
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->definitions);
    }
}
