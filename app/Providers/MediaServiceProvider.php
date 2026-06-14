<?php

namespace App\Providers;

use App\Services\Media\CloudinaryMediaService;
use App\Services\Media\LocalMediaService;
use App\Services\Media\MediaUploadService;
use App\Services\Media\PrivateFileService;
use Cloudinary\Cloudinary;
use Illuminate\Support\ServiceProvider;

class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Public images: Local in dev/testing (no Cloudinary account/network
        // required), Cloudinary in production. Switch via MEDIA_DRIVER.
        $this->app->bind(MediaUploadService::class, function ($app): MediaUploadService {
            $useLocal = config('media.driver') === 'local' || $app->environment('testing');

            if ($useLocal) {
                return new LocalMediaService();
            }

            return new CloudinaryMediaService(new Cloudinary(env('CLOUDINARY_URL')));
        });

        $this->app->singleton(PrivateFileService::class);
    }
}
