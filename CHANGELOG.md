# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2025-10-29

### Added
- **Automatic feed updates**: Scheduler task for background RSS feed updates
- **CLI command**: `mpcrss:updatefeeds` command for manual/cron-based feed updates
- **Isolated cache group**: RSS feeds use custom `mpc_rss` cache group, independent from page/system caches
- **Cache tags**: Improved cache management with tags (`mpc_rss`, `mpc_rss_feed`)

### Changed
- Documentation streamlined and duplicates removed
- Feed cache now uses dedicated cache group for isolated cache management

## [1.0.0] - 2025-10-26

### Added
- **init**: First version with grouping mode navigation customization