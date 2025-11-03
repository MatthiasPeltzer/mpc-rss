# Automatic Feed Updates

Keep RSS feeds fresh with background updates via Scheduler or CLI.

## Quick Setup: TYPO3 Scheduler (Recommended)

1. **Activate Scheduler Extension** (if needed): `Admin Tools → Extensions → scheduler`

2. **Create Task**: `System → Scheduler → Add Task`
   - **Class**: "Update RSS Feeds"
   - **Type**: Recurring Task
   - **Frequency**: `*/30 * * * *` (every 30 minutes)
   - **Cache Lifetime**: `3600` (1 hour)
   - **Clear cache before updating**: Unchecked

3. **Test**: Click Play icon to run immediately

## CLI Command

```bash
# Manual update
vendor/bin/typo3 mpcrss:updatefeeds

# Options
vendor/bin/typo3 mpcrss:updatefeeds --clear-cache
vendor/bin/typo3 mpcrss:updatefeeds --cache-lifetime=7200

# Crontab (alternative to scheduler)
*/30 * * * * cd /path/to/typo3 && vendor/bin/typo3 mpcrss:updatefeeds
```

## How It Works

- Fetches all configured RSS feed URLs from database
- Updates `mpc_rss` cache in background (isolated from page/system caches)
- Failed feeds don't stop others from updating
- Automatic deduplication of feed URLs

## Cache Isolation

| Action | RSS Cache | Page/System Cache |
|--------|-----------|-------------------|
| Scheduler runs | ✅ Updated | ❌ Untouched |
| Clear page cache | ❌ Untouched | ✅ Cleared |
| `cache:flush --group=mpc_rss` | ✅ Cleared | ❌ Untouched |

Page cache clearing won't affect RSS feeds - they stay cached for fast display.

## Recommended Settings

| Feed Type | Frequency | Cache Lifetime |
|-----------|-----------|----------------|
| News feeds | Every 15 min | 1800s (30 min) |
| Blogs | Every 30 min | 3600s (1 hour) |
| Daily digest | Hourly | 7200s (2 hours) |

**Tip:** Set cache lifetime ≥ update frequency for best performance.

## Troubleshooting

**Scheduler not running:**
- Check `System → Scheduler` for last execution time
- Setup scheduler cron: `*/5 * * * * /path/to/php /path/to/typo3/vendor/bin/typo3 scheduler:run`

**Feeds not updating:**
```bash
vendor/bin/typo3 mpcrss:updatefeeds --clear-cache
vendor/bin/typo3 cache:flush --group=mpc_rss
```
- Verify feed URLs are accessible
- Check logs: `System → Log` → Filter by "mpc_rss"

**Performance:** Don't run scheduler more frequently than every 15 minutes.
