# Imagery for Winter CMS

[![tests](https://github.com/bonniss/wn-imagery-plugin/actions/workflows/tests.yaml/badge.svg)](https://github.com/bonniss/wn-imagery-plugin/actions/workflows/tests.yaml)
[![Packagist](https://img.shields.io/packagist/v/bonniss26/wn-imagery-plugin)](https://packagist.org/packages/bonniss26/wn-imagery-plugin)

Show something in the right colours while your photos are still loading.

Add `|lqipStyle` to an image in a Twig template and it gets a low-quality
image placeholder (LQIP): a flat colour, a gradient or a blurred preview,
painted behind the image until the real photo arrives.

![The four modes on four photos](https://raw.githubusercontent.com/bonniss/lqip/main/docs/images/modes.jpg)

- **One filter.** It works with Media Library paths, `|media` URLs, file
  attachments, or `null`.
- **Cached in the database.** Each image is processed once, not on every
  page view.
- **Never breaks a page.** A missing or broken image gives an empty string,
  never an exception.
- Settings page, queued generation, console commands. GD or Imagick.

Built on [`bonniss26/lqip`](https://github.com/bonniss/lqip), which does the
image work and can also be used without Winter.

## Contents

- [Install](#install)
- [Use it in templates](#use-it-in-templates)
- [Choosing a mode](#choosing-a-mode)
- [Going to production](#going-to-production)
- [Settings](#settings) and [configuration](#configuration)
- [Housekeeping](#housekeeping)
- [For developers](#for-developers)

## Install

```bash
composer require bonniss26/wn-imagery-plugin
php artisan winter:up
```

Requirements: Winter CMS 1.2, PHP 8.0.2+, and `ext-gd` or `ext-imagick`.
Imagick is about 3.5× faster on large JPEGs; either works.

## Use it in templates

```twig
{# Most common: a placeholder behind an <img> #}
<img src="{{ photo|media }}" style="{{ photo|lqipStyle }}" loading="lazy">

{# Only the value, to combine with your own background-image #}
<div style="background-image: url({{ photo|media }}), {{ photo|lqip }}"></div>

{# Override the mode (or any option) for one call #}
<img src="{{ photo|media }}" style="{{ photo|lqipStyle({mode: 'blur'}) }}">
```

`photo` can be any of these:

- a Media Library path (`photos/a.jpg`);
- the output of `|media`;
- a `System\Models\File`;
- an `attachOne` / `attachMany` relation (the first file is used);
- `null`, which gives an empty string.

From PHP, use the `Imagery` facade:

```php
Imagery::style($post->featured_image);  // "background-image:linear-gradient(#b5b2a5,#b5b2a5)" or ""
Imagery::css('photos/a.jpg');           // "linear-gradient(#b5b2a5,#b5b2a5)" or ""
Imagery::result($file);                 // Bonniss26\Lqip\LqipResult or null
```

## Choosing a mode

Pick the mode in **Settings → Image placeholders**, or per call as above.

| Mode | Looks like | Size per image | Good for |
|---|---|---|---|
| `dominant_color` *(default)* | One flat colour | ~32 chars | Lightest; works everywhere |
| `blur` | A blurred thumbnail | ~130 chars | Looks most like the photo. Recommended for most sites |
| `gradient` | Four soft colour patches | ~256 chars | Servers whose GD/Imagick lacks WebP |
| `css_only` | Colour plus coarse layout | ~13 chars | Experimental. Needs your own decode stylesheet |

The [library's README](https://github.com/bonniss/lqip#choosing-a-mode)
explains each mode in more detail.

`|lqip` always returns a `background-image` value. If the mode is
`css_only`, which produces a CSS variable instead, `|lqip` uses
`dominant_color` so templates keep working. `|lqipStyle` renders every mode,
including `css_only` (`--lqip:87524`).

`blur` uses a `data:` URI. If your site sends a strict
Content-Security-Policy, allow `img-src data:` or the placeholder will not
show.

## Going to production

Generating a placeholder means decoding the original photo. That takes about
20 ms per megapixel with GD, so a 24 MP photo costs half a second. The plugin
therefore never generates without limits during a page render. When an image
has no placeholder yet:

| Your setup | What happens |
|---|---|
| A real queue (`database`, `redis`, ...) | A background job generates it; it appears on a later page view |
| Debug mode (`app.debug`), or `lazy` on | Generated during the render, at most 5 per request |
| Otherwise | No placeholder for that image until it is warmed |

So in production, **warm the cache** after deploying or importing images. You
can also schedule it:

```bash
php artisan imagery:warm            # Media Library images
php artisan imagery:warm --files    # ...and file attachments
```

It skips images that are already done and unchanged, so it is safe to run as
often as you like.

An image that fails, because it is corrupt or too large, is not retried on
every page view. It is retried after 15 minutes, then 30, and so on up to a
week, at most 5 times. Replacing the file starts over.

## Settings

**Settings → CMS → Image placeholders** sets the mode, the engine (GD or
Imagick) and the `blur` options. Leave a field blank to use the
configuration file's value; the blank option shows what that currently is.

Changes apply on the next page view. Nothing needs clearing, because the
options are part of the cache key. Switching engine keeps existing
placeholders, since both engines produce equivalent ones.

## Configuration

Override any key of [`config/config.php`](config/config.php) in your app at
`config/bonniss26/imagery/config.php`. The ones you are most likely to want:

| Key | Default | What it does |
|---|---|---|
| `generation` | library defaults | Default options: `mode`, `driver`, `long_edge`, `quality`, ... |
| `settings_override_config` | `true` | Set `false` to make this file win over the backend settings |
| `lazy` | `null` (follows `app.debug`) | Generate during page renders when no queue is set up |
| `request_budget` | `5` | Most placeholders generated in one request |
| `sources.allow_absolute_paths` | `false` | Accept raw filesystem paths, only inside `sources.allowed_roots` |
| `cache.remote_identity` | `full` | On S3 and similar disks, `path_only` skips checking each file's modification time |
| `queue.connection` | `null` (default queue) | Queue connection for generation jobs |

URLs to other sites are never fetched.

## Housekeeping

```bash
php artisan imagery:purge --failed     # retry failed images on the next view
php artisan imagery:purge --stale      # rows made with options no longer in use
php artisan imagery:purge --orphaned   # rows whose image no longer exists
php artisan imagery:purge --all
```

None of these is required; they reclaim database space.

---

## For developers

### How it is built

The plugin is a thin Winter layer over the library. Almost all of the logic is
in plain classes that are given their collaborators, so it is unit-tested
without booting Winter:

| Class | Responsibility |
|---|---|
| `classes/Imagery` | The service behind the filters and facade: look up the cache, decide what to do on a miss, record failures |
| `classes/OptionResolver` | Merges per-call overrides, settings, config and library defaults, in that order |
| `classes/SourceResolver` | Turns whatever a template passes into a `Source`. Refuses path traversal, foreign URLs and paths outside `allowed_roots` |
| `classes/CacheKey` | `sha1` of the source and the options that change the output. The driver is deliberately not one of them |
| `classes/RetryPolicy` | Exponential backoff for failed images |
| `classes/RequestBudget` | The per-request cap on inline generation |
| `classes/Defaults` | Option names, which ones change the output, available modes and drivers |

The Winter-specific parts are adapters behind interfaces: `WinterMediaStorage`
(`MediaStorage`), `DatabaseStore` + `models/Placeholder` (`PlaceholderStore`),
`jobs/GeneratePlaceholder`, the console commands, and `Plugin.php`, which
wires them together and registers the Twig filters.

### Running the tests

The library is resolved from a sibling checkout during development, through a
Composer path repository. Composer only reads `repositories` from the root
package, so this does not affect people installing the plugin.

```bash
git clone https://github.com/bonniss/lqip
git clone https://github.com/bonniss/wn-imagery-plugin
cd wn-imagery-plugin
composer install
composer test
```

The unit suite needs no Winter install. CI does the same: it clones the
library next to the plugin and runs the suite on PHP 8.0 to 8.4. Behaviour
inside Winter (filters, migrations, queue, commands) is checked by installing
the plugin into a Winter 1.2 app. There is no automated integration suite
yet.

### Contributing

- **Templates must never break.** Every public path returns `''` or `null` on
  failure instead of throwing.
- Policy belongs in the plain classes, with a unit test. The Winter adapters
  should stay thin.
- Image work belongs in [the library](https://github.com/bonniss/lqip), not
  here. A new mode or engine is a library change.
- Keep `composer test` green, one logical change per commit, and explain why
  in the commit message.

## License

MIT.
