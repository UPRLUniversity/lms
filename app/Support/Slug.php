<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Builds URL slugs that are unique within a table, appending -2, -3, … on collision.
 * Used for faculties, departments and courses so public URLs stay clean and stable.
 */
class Slug
{
    /**
     * @param  class-string<Model>  $model
     */
    public static function unique(string $model, string $source, string $column = 'slug', ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: Str::lower(Str::random(8));
        $slug = $base;
        $suffix = 2;

        while (self::exists($model, $column, $slug, $ignoreId)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * @param  class-string<Model>  $model
     */
    private static function exists(string $model, string $column, string $slug, ?int $ignoreId): bool
    {
        return $model::query()
            ->where($column, $slug)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();
    }
}
