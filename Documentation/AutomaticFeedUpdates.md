# Automatic Feed Updates

## Scheduler (recommended)

1. Go to **System > Scheduler > Add Task**
2. Select **"Update RSS Feeds"**, set to **Recurring**
3. Set frequency (e.g. `*/30 * * * *`) and cache lifetime (e.g. `3600`)
4. Click the play icon to test

## CLI

```bash
vendor/bin/typo3 mpcrss:updatefeeds
vendor/bin/typo3 mpcrss:updatefeeds --clear-cache
vendor/bin/typo3 mpcrss:updatefeeds --cache-lifetime=7200
```

As a crontab entry:

```
*/30 * * * * cd /path/to/typo3 && vendor/bin/typo3 mpcrss:updatefeeds
```

## Cache isolation

The RSS cache (`mpc_rss`) is independent of the page and system caches:

| Action | RSS cache | Page/system cache |
|--------|-----------|-------------------|
| Scheduler / CLI runs | Updated | Untouched |
| Clear page cache | Untouched | Cleared |
| `cache:flush --group=mpc_rss` | Cleared | Untouched |

## Recommended intervals

| Feed type | Update frequency | Cache lifetime |
|-----------|-----------------|----------------|
| News | 15 min | 1800 s |
| Blogs | 30 min | 3600 s |
| Daily digest | 60 min | 7200 s |

Set cache lifetime >= update frequency so visitors always hit a warm cache.

## Troubleshooting

**Scheduler not running** -- Verify the scheduler cron itself is active:
`*/5 * * * * vendor/bin/typo3 scheduler:run`

**Feeds not updating** -- Force a fresh fetch:

```bash
vendor/bin/typo3 mpcrss:updatefeeds --clear-cache
```

Check **System > Log** and filter by component `mpc_rss` for errors.
