# MPC RSS Extension

A modern TYPO3 extension for displaying RSS feeds with category filtering and pagination support.

## Features

- **Database-driven feed management**: Add RSS feeds directly in content elements via inline records
- **No XML configuration**: Pure PHP TCA configuration for modern TYPO3 development
- **Flexible source naming**: Auto-detection or custom source names for feeds
- **Category filtering**: Include/exclude specific categories
- **Pagination support**: Navigate through large feed collections
- **Caching**: Built-in feed caching for improved performance
- **Multi-feed aggregation**: Combine multiple RSS/Atom feeds in one view
- **Sortable feeds**: Drag-and-drop ordering of feeds in backend

## Installation

1. Install via Composer (if available) or copy to `_packages/mpc-rss/`
2. Activate the extension in the Extension Manager
3. Run database updates to create the `tx_mpcrss_domain_model_feed` table

## Usage

### Adding the Plugin

1. Create a new content element
2. Select "Plugin" → "MPC RSS Feed"
3. Click "Add Feed" to add RSS feed URLs
4. Configure display options (categories, pagination, etc.)

### Feed Configuration

Each feed can have:
- **Title**: Descriptive name for the feed
- **Feed URL**: Full URL to the RSS/Atom feed
- **Source Name**: Optional custom display label (e.g., "BBC News", "TechCrunch", "My Blog")
- **Description**: Optional notes about the feed

### Plugin Options

- **RSS Feeds**: Inline records - add as many feeds as needed with the "Add Feed" button
- **Default category to show**: Which category to display by default
- **Include categories**: Comma-separated list of categories to show (empty = all)
- **Exclude categories**: Comma-separated list of categories to hide
- **Max items per category**: Maximum number of items to display per category
- **Cache lifetime**: How long to cache feed data (in seconds)
- **Show category filter navigation**: Enable/disable category filter
- **Enable pagination**: Enable paginated view for single category
- **Items per page**: Number of items per page when pagination is enabled

## Architecture

### Database Structure

- `tx_mpcrss_domain_model_feed`: Stores feed configurations (title, URL, source name)
- Inline records attached to `tt_content` via `tt_content` field

### PHP Classes

- **Domain/Model/Feed.php**: Extbase domain model for feeds
- **Domain/Repository/FeedRepository.php**: Repository for fetching feeds
- **Controller/FeedController.php**: Main plugin controller
- **Service/FeedService.php**: Feed parsing and caching service

### No XML Configuration

This extension uses **pure PHP TCA configuration** instead of FlexForms:
- TCA configuration in `Configuration/TCA/tx_mpcrss_domain_model_feed.php`
- Content element configuration in `Configuration/TCA/Overrides/tt_content.php`
- No FlexForms XML required

## Predefined Feeds

The extension automatically extracts the source name from the feed URL's domain name (e.g., "example.com" becomes "Example"). You can override this by setting a custom Source Name for each feed.

Custom feeds can use custom source names via the "Source Name" field.

## Technical Requirements

- TYPO3 13.x
- PHP 8.1+
- Extbase/Fluid

## Caching

Feeds are cached in the TYPO3 caching framework with the cache key `mpc_rss`. Default cache lifetime is 1800 seconds (30 minutes) but can be configured per plugin instance.

## Template Customization

Templates can be found in:
- `Resources/Private/Templates/Feed/List.html`
- `Resources/Private/Layouts/Default.html`

Override these in your site package as needed.

## License

See LICENSE file.

