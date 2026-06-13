# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [v1.2.0]

### Security
- Added an SSRF guard in `FeedService`: feed URLs must use an http(s) scheme and
  resolve to public IP addresses. Private, loopback, link-local and reserved
  ranges (e.g. `127.0.0.1`, `10.0.0.0/8`, `169.254.169.254`, `::1`, `fc00::/7`)
  are refused before any request is made.
- Limited feed requests to at most 3 http/https redirects and capped the
  downloaded response body at 5 MB to mitigate redirect-based SSRF and
  memory-exhaustion (DoS) via oversized feeds.

### Fixed
- Completed `ext_tables.sql` so all `tt_content` plugin columns (grouping mode,
  category filters, max items, cache lifetime, filter/paginate toggles, items
  per page) are created on fresh installs.
- CLI command and Scheduler task now report a feed as failed when fetching or
  parsing fails, instead of always reporting success.

### Accessibility
- Article cards now expose a single card-wide link via `stretched-link`; the
  separate image link was removed so screen readers no longer announce two
  links per card.
- Pagination controls have translated accessible labels, the current page uses
  `aria-current="page"`, and the active filter pill uses `aria-current`.
- Section headings use stable ids wired via `aria-labelledby`; static empty
  states use `role="status"`.
- Added `:focus-visible` outlines and a `prefers-reduced-motion` fallback in
  `rss.css`.

### Changed
- The plugin `list` action is now cacheable; rendered output is cached per
  `filterCategory` / `page` variant via cHash while feed payloads remain in the
  dedicated `mpc_rss` cache.

### Added
- Unit tests for `FeedService` (URL/HTML sanitization, SSRF host guard,
  deduplication, grouping, source detection) with a standalone PHPUnit setup.

## [v1.0.0] - 2025-11-03

### Added
- RSS feed aggregation with inline feed management
- Automatic background updates via Scheduler or CLI command
- Multiple grouping modes (category, source, date, unified timeline)
- Category filtering and pagination support
- SEO-friendly URLs via Route Enhancer
- Isolated RSS cache independent from page/system caches
- Custom template support with fallback paths
- Multi-language support (German/English)