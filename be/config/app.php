<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Supported Content Locales
    |--------------------------------------------------------------------------
    |
    | Every locale the public site and the admin panel can serve content in.
    | The first entry is treated as the primary locale: content must always
    | be filled in for it, while the others may fall back to it.
    |
    */

    'supported_locales' => ['vi', 'en', 'zh'],

    /*
    |--------------------------------------------------------------------------
    | Locale Fallback Chains
    |--------------------------------------------------------------------------
    |
    | Which locale a record borrows from when it has no translation of its own,
    | in order, ending at the primary locale. This decides both the text a
    | reader sees AND the slug the record answers to, so /zh/about-us is the
    | Chinese address of a page that only exists in Vietnamese and English —
    | the same slug as /en/about-us, differing only in the prefix.
    |
    | Chinese borrows from English before Vietnamese on purpose: a reader who
    | asked for Chinese and cannot have it is better served by English, and an
    | English slug next to English text beats an English slug over Vietnamese
    | text. A locale missing from this list falls back to the primary one.
    |
    */

    'locale_fallbacks' => [
        'en' => ['vi'],
        'zh' => ['en', 'vi'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Locales That Do Not Get Their Own Slug
    |--------------------------------------------------------------------------
    |
    | These borrow the address of the first locale in their fallback chain that
    | has one, and the editor is not offered a field to override it.
    |
    | Chinese is here because `Str::slug()` keeps ASCII and drops everything
    | else, so a title written in Han characters reduces to an empty string —
    | 质量保证 has no ASCII in it at all. What is left to build an address from
    | is either a random string, unreadable and changing on every save, or the
    | name the record already has in another language. Mirroring picks the
    | second, and buys two more things: /zh/about-us is the same address whether
    | or not the page has been translated yet, so translating one never moves
    | it; and no two records can ever want the same /zh address, because the
    | English slugs they mirror are already unique.
    |
    */

    'mirrored_slug_locales' => ['zh'],

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
