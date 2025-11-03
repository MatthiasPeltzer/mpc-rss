# SEO-Friendly URLs (Route Enhancer)

Enable clean, SEO-friendly URLs instead of query parameters.

## URL Examples

**Before:** `/links/rss-feeds?tx_mpcrss_feed%5BfilterCategory%5D=Politik&tx_mpcrss_feed%5Bpage%5D=1`  
**After:** `/links/rss-feeds/politik/page-1`

## URL Patterns

- `/rss-feeds/` - Main page
- `/rss-feeds/politik` - Category filter
- `/rss-feeds/page-2` - Pagination
- `/rss-feeds/politik/page-2` - Category with pagination

## Configuration

Add to `config/sites/[your-site]/config.yaml`:

```yaml
routeEnhancers:
  MpcRssFeed:
    type: Extbase
    extension: MpcRss
    plugin: Feed
    routes:
      - routePath: /
      - routePath: '/page-{page}'
      - routePath: '/{category}'
      - routePath: '/{category}/page-{page}'
    defaults:
      page: '1'
    requirements:
      page: '\d+'
    aspects:
      category:
        type: StaticValueMapper
        map:
          politik: Politik
          wirtschaft: Wirtschaft
          kultur: Kultur
          # Add all your categories here
      page:
        type: StaticRangeMapper
        start: '1'
        end: '100'
```

## Adding Categories

Add new categories to the mapping:

```yaml
aspects:
  category:
    type: StaticValueMapper
    map:
      new-category: "New Category"  # slug: "Display Name"
```

Use lowercase slugs with hyphens for multi-word categories.

## Multilanguage Support

For multiple languages, use `localeMap`:

```yaml
localeMap:
  - locale: default
    map:
      politik: Politik
  - locale: en_.*
    map:
      politics: Politik  # English slug for same category
```

This allows `/rss-feeds/politik` (German) and `/en/rss-feeds/politics` (English).

## Troubleshooting

**URLs still show query parameters:**
- Clear all caches: `vendor/bin/typo3 cache:flush`

**404 errors on category pages:**
- Verify category name exists in mapping
- Check spelling matches exactly
- Clear caches

**Performance:** Route enhancers are cached and don't impact performance.

