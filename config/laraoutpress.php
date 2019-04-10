<?php declare(strict_types=1);
return [
    /*
   |--------------------------------------------------------------------------
   | LaraOutPress Settings
   |--------------------------------------------------------------------------
   |
   | LaraOutPress is disabled by default, when enabled is set to true in app.php.
   | You can override the value by setting enable to true or false instead of null.
   |
   */
    'enabled' => env('VRKANSAGARA_COMPRESS_ENABLED', false),

    'debug' => env('VRKANSAGARA_COMPRESS_DEBUG', false),

    'target_environment' => env('VRKANSAGARA_COMPRESS_ENVIRONMENT', ''),

    'middleware_class' => \Vrkansagara\LaraOutPress\Middleware\AfterMiddleware::class,

    'allowed_methods' => [
        'GET'
    ],
    'allowed_locales' => [
        'en'
    ]
];
