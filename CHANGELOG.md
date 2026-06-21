# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [v1.2.3]

### Fixed
- Images and links are now read correctly from namespaced feed elements. Attribute
  access on nodes reached via `children($namespace)` looked for a namespaced
  attribute (e.g. `media:url`) instead of the plain one, so `media:content` /
  `media:thumbnail` image URLs and Atom `<link href>` / `<category term>` values
  were silently dropped. Feeds such as taz.de now show their thumbnails again.

### Tests
- Added a PHPUnit unit suite for `FeedService` covering URL/HTML sanitization, the
  SSRF host guard, the IP-pinned `fetchFeedBody` fetch loop (success, manual
  redirect following, max-redirect cutoff, non-200, body-size limit, disallowed
  URLs), parsing/grouping/date logic and cache-hit orchestration.
- Added a TYPO3 testing-framework functional suite for `FeedController::listAction`
  (full frontend render: items, pagination, source/date/none grouping),
  `FeedRepository`, `UpdateFeedsCommand` and `UpdateFeedsTask`.
- Added `test:unit` / `test:functional` Composer scripts and the matching dev
  dependencies (`phpunit/phpunit`, `typo3/testing-framework`, `typo3/cms-scheduler`).

## [v1.2.2]

### Security
- Hardened XML parsing in `FeedService`: documents declaring a DTD (`<!DOCTYPE>`)
  are now rejected, and parsing no longer requests entity substitution
  (`LIBXML_NOENT`) or external DTD loading (`LIBXML_DTDLOAD`). This closes XXE and
  entity-expansion ("billion laughs") vectors that the previous flag combination
  did not actually prevent.
- Outgoing feed requests are now pinned to the validated public IP(s) via
  `CURLOPT_RESOLVE`, and redirects are followed manually so every hop is
  re-validated and re-pinned. This closes the DNS-rebinding / TOCTOU window where
  a host could resolve to a public address during validation and to an
  internal/loopback address when the socket is actually opened.
- External feed HTML is now sanitized with the TYPO3 `HtmlSanitizer` (restricted to
  `a`, `p`, `br`, `strong`, `em`, `b`, `i`; `a[href]` limited to http(s)) instead of
  a hand-rolled regex.

### Fixed
- Feed URL guard now actually rejects loopback/localhost hostnames up-front (the
  previous `127.`/`0.0.0` pattern was anchored with `$` and never matched).
- Successful-but-empty feeds are cached, so they are no longer re-fetched on every
  request.
- Frontend site settings (`maxItems`, `cacheLifetime`, grouping mode, etc.) are now
  mapped into `plugin.tx_mpcrss.settings`, so they take effect as site-wide defaults.
- Description teasers crop the plain text after stripping tags (previously cropping
  ran first and could cut mid-tag).
- Invalid (but non-empty) feed XML is now handled gracefully; the parse-failure
  guard checked the wrong sentinel (`=== false` instead of `=== null`) and could
  let an unparsable body through into a `TypeError`.
- Removed dead `rss.css` rules (the `[role="navigation"]` selector and `.badge`
  styles that had no matching markup) and aligned the navigation heading selector.

### Accessibility
- Filter navigation heading is now an `<h2>` so headings no longer descend
  illogically (h3 before h2).
- Group section ids include the content element uid to stay unique when multiple
  RSS plugins appear on one page.

### Added
- `paginateCategory` site setting (with EN/DE labels).
- Ships a default frontend Content Security Policy
  (`Configuration/ContentSecurityPolicies.php`) that extends `img-src` with the
  `https:` scheme for remote feed images; documented how to tighten it to specific
  hosts in a site package.

### Changed
- Marked the extension's own classes `final` and added `declare(strict_types=1)` to
  the remaining configuration files for consistency with the project conventions.

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