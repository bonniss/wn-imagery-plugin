# Imagery

Low-quality image placeholders (LQIP) for Winter CMS — something renders in the
right shape and colour while the real photo is still loading.

Wraps [`bonniss26/lqip`](https://github.com/bonniss26/lqip), which does the
pixel work. This package is everything Winter-shaped around it: resolving
whatever a template hands you into an image, caching the result, settings,
console commands, and Twig filters.

> **Status: in development.** Nothing below is usable yet.

## Install

```
composer require bonniss26/wn-imagery-plugin
```

## Planned surface

```twig
{# the bare value - composable into a background-image list #}
<div style="background-image: url({{ photo|media }}), {{ photo|lqip }}"></div>

{# the whole declaration, for the common case #}
<img src="{{ photo|media }}" style="{{ photo|lqipStyle }}">
```

Any of these can be piped in: a Media Library path, a `System\Models\File`, an
`attachOne`/`attachMany` value, or `null`. A broken or missing image yields an
empty string, never an exception — these run inside templates, where throwing
takes down the whole page.

## Development

The core library is consumed from a sibling checkout during development, via a
Composer path repository pointing at `../lqip`. Composer only reads
`repositories` from the root package, so this has no effect on anyone
installing the plugin as a dependency.

```
composer install
composer test        # standalone suite, no Winter needed
composer test:all    # adds the integration suite, which needs a Winter app
```

Most of the logic is deliberately written to run without a Winter application,
so the suite that covers the branching is the one that runs anywhere.

## License

MIT.
