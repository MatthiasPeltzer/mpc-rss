# MPC RSS Extension

A TYPO3 extension for displaying RSS feeds with automatic updates, grouping, and pagination.

## Features

- **Inline feed management** - Add feeds directly in content elements
- **Automatic updates** - Background refresh via Scheduler or CLI
- **Isolated caching** - Independent RSS cache, doesn't affect page cache
- **Multiple grouping modes** - By category, source, date, or timeline
- **Pagination** - Handle large feed collections
- **SEO-friendly URLs** - Speaking URLs via Route Enhancer

## Requirements

- TYPO3 13.x - 14.x
- PHP 8.2+

## Installation

```bash
composer require mpc/mpc-rss
# or copy to typo3conf/ext/mpc_rss/
```

Activate in Extension Manager and run database updates.

## Quick Start

1. Create content element → Plugin → "MPC RSS Feed"
2. Click "Add Feed" and enter RSS/Atom feed URLs
3. Configure grouping mode and display options

## Automatic Updates

### Scheduler (Recommended)

```
System → Scheduler → Add Task
Class: "Update RSS Feeds"
Frequency: */30 * * * * (every 30 minutes)
```

### CLI Command

```bash
vendor/bin/typo3 mpcrss:updatefeeds
# Add to crontab: */30 * * * * cd /path/to/typo3 && vendor/bin/typo3 mpcrss:updatefeeds
```

See [Automatic Feed Updates](Documentation/AutomaticFeedUpdates.md) for details.

## Documentation

- [Automatic Feed Updates](Documentation/AutomaticFeedUpdates.md) - Setup scheduler and CLI commands
- [Custom Templates](Documentation/CustomTemplates.md) - Customize frontend display and navigation
- [Grouping Modes](Documentation/GroupingModes.md) - Organize feeds by category, source, or date
- [Routing](Documentation/Routing.md) - Configure SEO-friendly URLs
- [Architecture](Documentation/Architecture.md) - Technical design decisions

## License

GPL-2.0-or-later
