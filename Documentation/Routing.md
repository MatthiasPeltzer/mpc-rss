# SEO-Friendly URLs

Replace query parameters with clean URL segments.

**Before:** `/rss-feeds?tx_mpcrss_feed[filterCategory]=Politik&tx_mpcrss_feed[page]=1`
**After:** `/rss-feeds/politik/page-1`

## Configuration

Add to `config/sites/<your-site>/config.yaml`:

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
      page:
        type: StaticRangeMapper
        start: '1'
        end: '100'
```

Add every category you use to the `map`. Format: `url-slug: "Display Name"`.

## Multilanguage

The extension translates generated group labels (Today/Heute, General/Allgemein, etc.) automatically via XLF. Category names from RSS feeds pass through as-is.

For multilingual route slugs, use `localeMap`:

```yaml
aspects:
  category:
    type: StaticValueMapper
    map:
      politik: Politik
    localeMap:
      - locale: en_.*
        map:
          politics: Politik
```

> When using **date** or **source** grouping, the group names in URLs follow the
> frontend language. Adjust your `StaticValueMapper` accordingly.

## Troubleshooting

- **Query params still visible:** Clear all caches (`vendor/bin/typo3 cache:flush`).
- **404 on category pages:** Check that the category name in the map matches exactly.
