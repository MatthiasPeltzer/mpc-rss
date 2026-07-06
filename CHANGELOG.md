# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed
- `warmCache()` no longer rejects cached feeds when the URL fails SSRF DNS validation; validation applies only on cache misses.

### Changed
- Declare `extra.typo3/cms.Package.providesPackages` in `composer.json` for TYPO3 v14.3 metadata.
- Switch PHP-CS-Fixer ruleset from `@PER-CS1.0` to `@PER-CS1x0` and exclude `node_modules` / vendor paths from CS scans.

## [1.2.4] - 2026-06-22

- Add Changelog

## [1.2.3] - 2026-06-21

### Fixed
- Extract images and links from namespaced feed elements.

### Tests
- Functional suite for the repository and update command.
- Coverage for bounded body reading, IP resolution and fetch orchestration.
- Coverage for `FeedController::listAction` with a frontend request.
- Coverage for pagination, grouping modes and the scheduler task.
- Coverage for the SSRF fetch loop and remaining controller branches.

## [1.2.2] - 2026-06-21

### Security
- Hardened RSS fetching: SSRF IP-pinning, CSP and an XML parse fix.

## [1.2.1] - 2026-06-13

### Changed
- Added `.editorconfig`.
- Normalized line endings to LF and added `.gitattributes`.

## [1.2.0] - 2026-06-11

### Security
- Hardened security, schema and accessibility.

## [1.1.11] - 2026-06-11

### Changed
- Removed deprecations.

## [1.0.10] - 2026-03-14

### Changed
- Modernized the codebase and added a backend preview.

### Security
- Security hardening.

## [1.0.9] - 2025-12-21

### Fixed
- TYPO3 14 TCA migrations and deprecation fixes.

### Removed
- Deprecated `ext_tables_static+adt.sql`.
- "Spiegel" from the description.

## [1.0.8] - 2025-12-21

### Fixed
- TYPO3 14 icon registry.

## [1.0.7] - 2025-12-13

### Changed
- Maintenance release (version bump only).

## [1.0.6] - 2025-12-07

### Fixed
- Removed TCA warnings.

## [1.0.5] - 2025-11-12

### Fixed
- composer: corrected the `typo3-ter/mpc-rss` `self.version`.

## [1.0.4] - 2025-11-12

### Added
- Packagist and TYPO3 TER distribution.

### Fixed
- Log feed-fetch failures and cache empty results.

## [1.0.3] - 2025-11-08

### Added
- Complete German translations.

## [1.0.2] - 2025-11-04

### Changed
- Maintenance release (version bump only).

## [1.0.1] - 2025-11-04

### Fixed
- `use` statements.

## [1.0.0] - 2025-11-03

### Added
- Initial release: RSS feed content element with automatic feed updates via a scheduler task, documentation, license and extension icon.

[1.2.4]: https://github.com/MatthiasPeltzer/mpc-rss/compare/v1.2.3...v1.2.4
[1.2.3]: https://github.com/MatthiasPeltzer/mpc-rss/compare/v1.2.2...v1.2.3
[1.2.2]: https://github.com/MatthiasPeltzer/mpc-rss/compare/v1.2.1...v1.2.2
[1.2.1]: https://github.com/MatthiasPeltzer/mpc-rss/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/MatthiasPeltzer/mpc-rss/compare/v1.1.11...v1.2.0
[1.1.11]: https://github.com/MatthiasPeltzer/mpc-rss/compare/v1.0.10...v1.1.11
[1.0.10]: https://github.com/MatthiasPeltzer/mpc-rss/compare/v1.0.9...v1.0.10
[1.0.9]: https://github.com/MatthiasPeltzer/mpc-rss/compare/v1.0.8...v1.0.9
[1.0.8]: https://github.com/MatthiasPeltzer/mpc-rss/compare/v1.0.7...v1.0.8
[1.0.7]: https://github.com/MatthiasPeltzer/mpc-rss/compare/v1.0.6...v1.0.7
[1.0.6]: https://github.com/MatthiasPeltzer/mpc-rss/compare/v1.0.5...v1.0.6
[1.0.5]: https://github.com/MatthiasPeltzer/mpc-rss/compare/v1.0.4...v1.0.5
[1.0.4]: https://github.com/MatthiasPeltzer/mpc-rss/compare/v1.0.3...v1.0.4
[1.0.3]: https://github.com/MatthiasPeltzer/mpc-rss/compare/v1.0.2...v1.0.3
[1.0.2]: https://github.com/MatthiasPeltzer/mpc-rss/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/MatthiasPeltzer/mpc-rss/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/MatthiasPeltzer/mpc-rss/releases/tag/v1.0.0
