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
     * @return Collection<int, Media>
     */
    public function mediaFor(MediaPurpose $purpose): Collection
    {
        return $this->media()->where('purpose', $purpose->value)->get();
    }

    /**
     * The first/single media for a purpose (e.g. an avatar or cover image).
     */
    public function firstMediaFor(MediaPurpose $purpose): ?Media
    {
        return $this->media()->where('purpose', $purpose->value)->latest('id')->first();
    }
}
