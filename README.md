# MPC RSS Extension

A modern TYPO3 extension for displaying RSS feeds with automatic updates, category filtering, and pagination.

## Features

- **Database-driven feed management** with inline records
- **Automatic background updates** via Scheduler or CLI
- **Isolated caching** - RSS cache independent from page caches
- **Category filtering** - Include/exclude specific categories
- **Multiple grouping modes** - By category, source, date, or unified timeline
- **Pagination support** - Navigate large feed collections
- **Multi-feed aggregation** - Combine RSS/Atom feeds

## Requirements

- TYPO3 13.x
- PHP 8.1+

## Installation

1. Install via Composer or copy to `typo3conf/ext/mpc_rss/`
2. Activate in Extension Manager
3. Run database updates

## Quick Start

1. Create content element → Plugin → "MPC RSS Feed"
2. Add feed URLs with "Add Feed" button
3. Configure display options

### Feed Configuration

- **Feed URL**: Full RSS/Atom feed URL
- **Source Name**: Optional display name (auto-detected if empty)
- **Max items per category**: Limit items shown
- **Cache lifetime**: How long to cache (seconds)

## Automatic Updates

Keep feeds fresh without manual cache clearing:

### Option 1: Scheduler (Recommended)

```
System → Scheduler → Add new task
Class: "Update RSS Feeds"
Frequency: */30 * * * * (every 30 minutes)
```

### Option 2: CLI Command

```bash
vendor/bin/typo3 mpcrss:updatefeeds
```

**Add to crontab:**
```bash
*/30 * * * * cd /path/to/typo3 && vendor/bin/typo3 mpcrss:updatefeeds
```

**Details:** See [Documentation/AutomaticFeedUpdates.md](Documentation/AutomaticFeedUpdates.md)

## Caching

- **Cache lifetime**: Configurable per content element (default: 1800s)
- **Isolated cache group**: `mpc_rss` - independent from page/system caches
- **Cache tags**: `mpc_rss`, `mpc_rss_feed` for targeted clearing
- **Automatic management**: Scheduler/CLI handles updates

**Clear RSS cache only:**
```bash
vendor/bin/typo3 cache:flush --group=mpc_rss
```

## Template Customization

Override in your site package:
- `Resources/Private/Templates/Feed/List.html`
- `Resources/Private/Layouts/Default.html`

## Documentation

- [Automatic Feed Updates](Documentation/AutomaticFeedUpdates.md)
- [Architecture](Documentation/Architecture.md)
- [Custom Templates](Documentation/CustomTemplates.md)
- [Grouping Modes](Documentation/GroupingModes.md)
- [Navigation Customization](Documentation/NavigationCustomization.md)
- [Routing](Documentation/Routing.md)

## License

See LICENSE file.
