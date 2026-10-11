# Changelog

All notable changes to this project are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

- Requires Winter 1.2, PHP 8.0.2+, `bonniss26/lqip` ^0.1, and `ext-gd` or
  `ext-imagick`.

[Unreleased]: https://github.com/bonniss/wn-imagery-plugin/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/bonniss/wn-imagery-plugin/releases/tag/v1.0.0
