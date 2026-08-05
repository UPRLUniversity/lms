<?php

use App\Providers\AppServiceProvider;
use App\Providers\MediaServiceProvider;
use App\Providers\SettingsServiceProvider;

return [
    // Settings boots FIRST: it pushes stored settings into config (brand, commerce,
    // session, uploads), and the providers after it read those values.
    SettingsServiceProvider::class,
    AppServiceProvider::class,
    MediaServiceProvider::class,
];
