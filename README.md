# MPC RSS

TYPO3 extension for displaying RSS/Atom feeds with grouping, pagination, and background updates.

## Requirements

- TYPO3 13.x or 14.x
- PHP 8.2+

## Installation

```bash
composer require mpc/mpc-rss
```

Activate in the Extension Manager, then run **Maintenance > Analyze Database Structure**.

## Quick Start

1. Add a content element: **Plugin > MPC RSS Feed**
2. Click **Add Feed** and enter one or more RSS/Atom URLs
3. Choose a grouping mode and save

Feeds are stored as inline records directly on the content element -- no storage page needed.

## Background Updates

Feeds are cached per URL. To keep them fresh without making visitors wait:

**Scheduler** (recommended):
`System > Scheduler > Add Task > "Update RSS Feeds"` -- set frequency to e.g. every 30 minutes.

**CLI**:

```bash
vendor/bin/typo3 mpcrss:updatefeeds
vendor/bin/typo3 mpcrss:updatefeeds --clear-cache --cache-lifetime=3600
```

See [Automatic Feed Updates](Documentation/AutomaticFeedUpdates.md) for details and troubleshooting.

## Architecture

```
Request
  └─ FeedController::listAction()
       ├─ FeedRepository → inline feed records from tt_content
       └─ FeedService::fetchGroupedByCategory()
            ├─ fetchFeedItems()       per-URL HTTP fetch + XML parse + cache
            ├─ deduplicateItems()     link-based dedup across feeds
            ├─ groupItems()           category / source / date / none
            └─ sortAndSliceGroups()   date-desc sort + maxItems limit
```

**Key design decisions:**
- Dedicated CType (`mpcrss_feed`) via `PLUGIN_TYPE_CONTENT_ELEMENT`, not list_type
- IRRE inline records -- feeds belong to the content element, not a storage page
- Isolated cache (`mpc_rss`) -- clearing page cache doesn't affect feed data
- External content is sanitized at the caching layer (tags, attributes, URL schemes)
- Service layer is language-neutral; the controller translates generated group labels via XLF
- `warmCache()` skips grouping/sorting -- efficient for CLI and Scheduler

## Documentation

- [Automatic Feed Updates](Documentation/AutomaticFeedUpdates.md) -- Scheduler and CLI setup
- [Grouping Modes](Documentation/GroupingModes.md) -- Category, source, date, or timeline
- [Custom Templates](Documentation/CustomTemplates.md) -- Override templates and available variables
- [Routing](Documentation/Routing.md) -- SEO-friendly URLs

## License

GPL-2.0-or-later
