<?php

namespace App\Models\Concerns;

use App\Enums\MediaPurpose;
use App\Models\Media;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gives an owning model a polymorphic media() relation plus helpers. This is the
 * single attachment system for the whole app — feature sections reuse it rather
 * than inventing their own.
 */
trait HasMedia
{
    /**
     * @return MorphMany<Media, $this>
     */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    /**
     * Associate an existing (e.g. previously uploaded) Media record with this owner.
     */
    public function attachMedia(Media $media): Media
    {
        $this->media()->save($media);

        return $media;
    }

    /**
     * All media for a given purpose.
     *
     * Reads the eager-loaded relation when there is one. Without this the catalogue
     * eager-loads `media` and then throws the result away — every cover image on a
     * list page fired its own query, which is exactly the N+1 the eager load exists
     * to prevent.
     *
     * @return Collection<int, Media>
     */
    public function mediaFor(MediaPurpose $purpose): Collection
    {
        if ($this->relationLoaded('media')) {
            return $this->media->where('purpose', $purpose->value)->values();
        }

        return $this->media()->where('purpose', $purpose->value)->get();
    }

    /**
     * The first/single media for a purpose (e.g. an avatar or cover image).
     */
    public function firstMediaFor(MediaPurpose $purpose): ?Media
    {
        if ($this->relationLoaded('media')) {
            // sortByDesc('id') rather than latest('id') — same "newest wins" rule as the
            // query branch, applied in memory.
            return $this->media->where('purpose', $purpose->value)->sortByDesc('id')->first();
        }

        return $this->media()->where('purpose', $purpose->value)->latest('id')->first();
    }
}
