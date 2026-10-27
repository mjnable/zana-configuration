# Changelog

All Zana packages share one version number and are released together. A release that did
not change this package is not listed.

## 2.5.1 — unreleased

### Fixed

- A quoted `.env` value is always a string (FE-42). `DotEnv` remembers the variables it set from
  quoted values, and `ConfigurationBuilder::addEnvironment()` no longer converts them: a phone number
  such as `"+243812345678"` or an all-digit password no longer becomes a float that `string()` refuses.

### Changed

- Unquoted `APP_*` values with a leading `+` or `0`, or integers longer than 15 digits, stay strings.
  Only plain integers and decimals (`24`, `-3`, `0.5`, `2e3`) become numbers.
- `string()` returns an int as text; `bool()` accepts `"true"`, `"false"`, `"1"` and `"0"` (any case).
- `#[Config]` injection converts the value to a `string`, `int`, `float` or `bool` parameter with the
  typed readers.
## 2.4.0 — 2026-10-26

### Changed

- Local development pins the sibling packages at 2.4.0. No change to the package itself.

## 2.3.0 — 2026-10-26

### Changed

- Tests and tooling (`tests/`, `phpunit.xml.dist`, `phpstan.neon.dist`) are no longer in Composer
  archives, so they stay out of production `vendor/` folders (FE-17).

## 2.0.0 — 2026-10-26

The final 2.0 release. No changes since 2.0.0-rc.1.

## 2.0.0-alpha.3 — 2026-10-24

First release of the 2.0 line. Configuration from PHP files, `.env` files and environment variables, merged into one immutable tree, with typed accessors and `#[Config]` injection. Requires PHP 8.5 or later.

Documentation: [the configuration guide](https://github.com/mjnable/zana-docs/blob/main/guides/configuration.md).
