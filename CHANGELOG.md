# Changelog

All notable changes to this project are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2026-10-12

Requires `bonniss26/lqip` ^0.3.

### Added

- **Placeholders are made on upload.** An image uploaded through the Media
  Manager or attached as a file gets its placeholder straight away - queued
  if a real queue exists, otherwise during the upload. Renaming, moving and
  deleting Media Library files, and deleting attachments, keep the cache in
  step. Config key `generate_on_upload`, on by default.
- The image size is cached beside the placeholder (migration 1.1.0):
  `|lqipSize` / `Imagery::dimensions()` return `{width, height}` without
  reading the file, and `|lqipStyle({aspect_ratio: true})` reserves the shape.
  The size is the displayed one, after EXIF rotation.
- `thumbhash` mode, for APIs: `Imagery::result()` returns the hash; both
  filters fall back to `dominant_color` for it.
- *Smooth* (`smooth`) and *Background for transparent images* (`background`)
  settings and config keys, passed to the library.

### Changed

- Transparent images keep their transparency instead of being placed on black
  (from lqip 0.3).
- `|lqipStyle` output now ends in `background-size:cover`, so the `blur`
  placeholder stretches over the element instead of tiling at 16 px.
- `imagery:warm` regenerates rows written by 1.0, which have no size.

### Upgrading

Run `php artisan winter:up`, then `php artisan imagery:warm` once. Cache keys
written by 1.0 stay valid, so placeholders keep showing in between.

## [1.0.0] - 2026-10-11

First release.

### Added

- `|lqip` and `|lqipStyle` Twig filters and an `Imagery` facade, accepting a
  Media Library path, the output of `|media`, a `System\Models\File`, an
  attachment relation, or `null`. Failures render as an empty string.
- Database cache keyed on the source and only the options that change the
  output for the mode, with in-place overwrite detection and an exponential
  backoff for images that fail.
- Generation on a real queue when one is configured; otherwise inline when
  `lazy` is on, capped by a per-request budget.
- Backend settings page in which every field can inherit from the config file
  and the library; the engine dropdown lists only engines installed on the
  server.
- `imagery:warm` and `imagery:purge` console commands.
- English and Vietnamese translations.

### Notes

- Requires Winter 1.2, PHP 8.0.2+, `bonniss26/lqip` ^0.2, and `ext-gd` or
  `ext-imagick`.

[Unreleased]: https://github.com/bonniss/wn-imagery-plugin/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/bonniss/wn-imagery-plugin/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/bonniss/wn-imagery-plugin/releases/tag/v1.0.0
