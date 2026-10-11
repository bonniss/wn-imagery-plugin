# Imagery

Low-quality image placeholders (LQIP) for Winter CMS: something renders in the
right shape and colour while the real photo is still loading.

Wraps [`bonniss26/lqip`](https://github.com/bonniss/lqip), which does the
pixel work on GD or Imagick. This plugin is everything Winter-shaped around
it: resolving whatever a template hands you into an image, caching the result
in the database, a settings page, queued generation, console commands, and
Twig filters.

## Install

```bash
composer require bonniss26/wn-imagery-plugin
php artisan winter:up
```

Until both packages are listed on Packagist, add their GitHub repositories to
your project's `composer.json` first:

```bash
composer config repositories.lqip vcs https://github.com/bonniss/lqip
composer config repositories.imagery vcs https://github.com/bonniss/wn-imagery-plugin
composer require bonniss26/wn-imagery-plugin:^1.0
php artisan winter:up
```

Requirements: Winter 1.2, PHP 8.0.2+, and `ext-gd` or `ext-imagick`.

## Use

```twig
{# The bare value - composable into a background-image list #}
<div style="background-image: url({{ photo|media }}), {{ photo|lqip }}"></div>

{# The whole declaration, for the common case #}
<img src="{{ photo|media }}" style="{{ photo|lqipStyle }}" loading="lazy">

{# Per-call options override settings and config #}
<img src="{{ photo|media }}" style="{{ photo|lqipStyle({mode: 'blur'}) }}">
```

Any of these can be piped in: a Media Library path (`photos/a.jpg`), the
output of `|media`, a `System\Models\File`, an `attachOne`/`attachMany` value
(the first file is used), or `null`. A broken or missing image yields an empty
string, never an exception - these run inside templates, where throwing takes
down the whole page.

From PHP, the `Imagery` facade does the same:

```php
Imagery::css($post->featured_image);    // "linear-gradient(#7c6a54,#7c6a54)" or ""
Imagery::style('photos/a.jpg');         // "background-image:..." or ""
Imagery::result($file);                 // Bonniss26\Lqip\LqipResult or null
```

`|lqip` always returns a `background-image` value. If the configured mode is
the experimental `css_only` - which produces a custom property instead - it
uses the dominant colour, so an editor changing the mode cannot break a
template. `|lqipStyle` renders every mode, `css_only` included
(`--lqip:192900`).

## When placeholders are generated

Generating one decodes the original image: about 21 ms per source megapixel
with GD, far less with Imagick for JPEG. So a page render never generates
without limits. On a cache miss:

| Situation | What happens |
|---|---|
| A real queue is configured (`database`, `redis`, ...) | A job is pushed; the placeholder appears on a later render |
| `lazy` is on (default: when `app.debug` is on) | Generated inline, at most `request_budget` (5) per request |
| Otherwise | Nothing is shown for that image until it is warmed |

In production, fill the cache ahead of time, e.g. after a deploy or from the
scheduler:

```bash
php artisan imagery:warm            # Media Library images
php artisan imagery:warm --files    # ...and file attachments
```

It skips anything already cached and unchanged, so it is safe to re-run.

A failed image is recorded and retried with an exponential backoff (15
minutes, doubling, up to a week, at most 5 times), not decoded again on every
render. Replacing the file resets it.

## Settings

**Settings → CMS → Image placeholders** sets the mode, engine, and the
blur-mode options. Every field can be left blank to inherit the config file,
and from there the library's defaults; the blank option shows what it
currently resolves to.

A change takes effect on the next render - options are part of the cache key,
so nothing needs clearing. Switching engine (GD/Imagick) keeps existing
placeholders, since both produce equally valid ones.

## Configuration

Publish nothing: override any key of
[`config/config.php`](config/config.php) in your app at
`config/bonniss26/imagery/config.php`. The interesting ones:

- `generation` - default options for the library (`mode`, `driver`, ...).
- `settings_override_config` - set `false` on a configuration-as-code site,
  so this file wins over the backend form.
- `lazy`, `request_budget` - see above.
- `sources.allow_absolute_paths` / `sources.allowed_roots` - raw filesystem
  paths are refused unless enabled, and then only inside these directories.
  Foreign URLs are never fetched.
- `cache.remote_identity` - on a remote disk (S3...), `full` checks each
  image's modification time on every render to notice in-place overwrites;
  `path_only` skips that round trip.
- `queue.connection` - which queue to push jobs to.

## Housekeeping

```bash
php artisan imagery:purge --failed     # retry failed images on the next render
php artisan imagery:purge --stale      # rows written under options no longer in use
php artisan imagery:purge --orphaned   # rows whose image no longer exists
php artisan imagery:purge --all
```

None of these is needed for correctness; they reclaim space.

## Content-Security-Policy

The `blur` mode emits a `data:` URI, so a strict CSP needs `img-src data:`.
The other modes emit plain CSS colours.

## Development

The core library is consumed from a sibling checkout during development, via a
Composer path repository pointing at `../lqip`. Composer only reads
`repositories` from the root package, so this has no effect on anyone
installing the plugin as a dependency.

```bash
composer install
composer test        # standalone suite, no Winter needed
composer test:all    # adds the integration suite, which needs a Winter app
```

Almost all of the logic - option precedence, cache keys, source resolution and
its security checks, the miss/queue/budget decisions, backoff - is written
against injected collaborators, so the suite that covers the branching runs
anywhere.

## License

MIT.
