<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | When false, every filter returns an empty string and nothing is resolved,
    | queried or generated. Useful for isolating a performance problem without
    | editing templates.
    |
    */

    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Generation options
    |--------------------------------------------------------------------------
    |
    | Passed straight to bonniss26/lqip. Anything omitted here falls back to
    | that library's own defaults, so this array only needs to hold what you
    | actually want to differ.
    |
    | mode:      dominant_color | gradient | blur | css_only
    | long_edge: longest edge in px of the miniature (blur only), capped at 64
    | quality:   lossy encode quality 0-100 (blur only)
    | blur:      bake a Gaussian blur into the pixels (blur only)
    | format:    webp | jpg (blur only). Without WebP in GD, jpg costs ~9x more.
    |
    */

    'generation' => [
        // 'mode' => 'dominant_color',
        // 'long_edge' => 16,
        // 'quality' => 25,
        // 'blur' => true,
        // 'format' => 'webp',
    ],

    /*
    |--------------------------------------------------------------------------
    | Where options come from
    |--------------------------------------------------------------------------
    |
    | By default the backend settings page wins over this file, so an editor can
    | change the look without a deploy. Set this to false on a
    | configuration-as-code site, where the file should be the source of truth
    | and the settings form is read-only in practice.
    |
    */

    'settings_override_config' => true,

    /*
    |--------------------------------------------------------------------------
    | Generating during a page render
    |--------------------------------------------------------------------------
    |
    | Generation costs roughly 21ms per megapixel of the source, because
    | decoding the original dominates. A gallery page full of uncached 10MP
    | photos can therefore blow the request timeout on its own.
    |
    | 'lazy' decides whether a cache miss generates inline. null means "follow
    | app.debug": generate during development so things just work, and rely on
    | the imagery:warm command in production. 'request_budget' caps how many
    | images a single request will ever generate; past it, the filter returns
    | nothing and the image is left for warming. A missing placeholder is a
    | cosmetic problem, a timed-out request is not.
    |
    */

    'lazy' => null,

    'request_budget' => 5,

    /*
    |--------------------------------------------------------------------------
    | Sources
    |--------------------------------------------------------------------------
    |
    | Media Library paths, File models and attachment relations are always
    | accepted. Raw filesystem paths are not, because a filter reachable from a
    | template turns any user-controlled string into a way to probe the
    | filesystem. Enable it only with allowed_roots set to the directories you
    | actually intend to expose.
    |
    */

    'sources' => [
        'allow_absolute_paths' => false,
        'allowed_roots' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Failures are recorded as rows rather than as absences, so a broken image
    | backs off instead of being retried on every single render. The backoff
    | doubles from base_minutes up to max_minutes, and after max_attempts the
    | entry stops being retried at all until imagery:purge --failed clears it.
    |
    | remote_identity: on a non-local disk, reading mtime and size costs a
    | round trip per image. 'full' pays it and detects an overwrite in place.
    | 'path_only' skips it, at the cost of needing an explicit purge when a
    | file is replaced under the same name.
    |
    */

    'cache' => [
        'retry' => [
            'max_attempts' => 5,
            'base_minutes' => 15,
            'max_minutes' => 10080,
        ],
        'remote_identity' => 'full',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | When the resolved queue connection uses a real driver, generation is
    | pushed to a job instead of running inline. On a 'sync' connection - the
    | Winter default, and what most shared hosting ends up with - it runs
    | inline regardless, so this needs no configuration to be safe.
    |
    | null uses the application's default connection.
    |
    */

    'queue' => [
        'connection' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Register the Imagery alias
    |--------------------------------------------------------------------------
    |
    | Adds a global `Imagery` class alias. It is a generic name for a plugin to
    | claim, so it can be turned off; the facade class itself always works.
    |
    */

    'register_alias' => true,

];
