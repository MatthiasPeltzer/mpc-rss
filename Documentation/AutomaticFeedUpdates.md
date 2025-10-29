# Automatic RSS Feed Updates

Ensure visitors always see fresh content without manual cache clearing.

## Quick Setup: TYPO3 Scheduler (Recommended)

1. **Install Scheduler Extension** (if needed): `Admin Tools → Extensions → "scheduler" → Activate`

2. **Create Task**: `System → Scheduler → Add new task (+)`

3. **Configure**:
   - **Class**: "Update RSS Feeds"
   - **Type**: Recurring Task
   - **Frequency**: `*/30 * * * *` (every 30 minutes)
   - **Cache Lifetime**: `3600` (1 hour)
   - **Clear cache before updating**: Unchecked

4. **Test**: Save, then click Play icon to run immediately

✅ Done! Feeds update automatically every 30 minutes.

## Alternative: CLI Command

```bash
# Test manually
vendor/bin/typo3 mpcrss:updatefeeds

# Options
vendor/bin/typo3 mpcrss:updatefeeds --clear-cache           # Force refresh
vendor/bin/typo3 mpcrss:updatefeeds --cache-lifetime=7200   # 2 hour cache

# Add to crontab
*/30 * * * * cd /path/to/typo3 && vendor/bin/typo3 mpcrss:updatefeeds
```

## How It Works

- **Fetches all configured RSS feed URLs** from the database
- **Updates cache** with fresh content in the background
- **Isolated cache**: Only touches `mpc_rss` cache, never page/system caches
- **Error handling**: Failed feeds don't stop others from updating
- **Automatic deduplication**: Same feed URLs are handled efficiently

## Cache Behavior

The RSS cache is **completely isolated**:

| Action | RSS Cache | Page/System Cache |
|--------|-----------|-------------------|
| Scheduler runs | ✅ Updated | ❌ Untouched |
| Clear page cache | ❌ Untouched | ✅ Cleared |
| `cache:flush` | ✅ Cleared | ✅ Cleared |
| `cache:flush --group=mpc_rss` | ✅ Cleared | ❌ Untouched |

**Result**: Clearing page caches won't affect RSS feeds - they stay cached for fast display.

## Recommended Settings

| Feed Type | Frequency | Cache Lifetime |
|-----------|-----------|----------------|
| News feeds | Every 15 min (`*/15 * * * *`) | 1800s (30 min) |
| Blogs | Every 30 min (`*/30 * * * *`) | 3600s (1 hour) |
| Daily digest | Hourly (`0 * * * *`) | 7200s (2 hours) |

**Tip**: Set cache lifetime ≥ update frequency for best performance.

## Troubleshooting

### Scheduler Task Not Running

- **Check**: `System → Scheduler` - verify last execution time
- **Setup scheduler cron** (if not done):
  ```bash
  */5 * * * * /path/to/php /path/to/typo3/vendor/bin/typo3 scheduler:run
  ```

### Feeds Not Updating

```bash
# Test manually
vendor/bin/typo3 mpcrss:updatefeeds

# Force refresh
vendor/bin/typo3 mpcrss:updatefeeds --clear-cache

# Check specific cache group
vendor/bin/typo3 cache:flush --group=mpc_rss
```

- **Verify feed URLs** are correct and accessible
- **Check logs**: `System → Log` → Filter by "mpc_rss"
- **Test feed URLs** manually in browser

### Performance Tips

- Don't run scheduler too frequently (minimum: 15 minutes)
- Match frequency to actual feed update patterns
- Balance number of feeds with update frequency

## Manual Cache Clearing

```bash
# Clear only RSS cache (recommended)
vendor/bin/typo3 mpcrss:updatefeeds --clear-cache

# Or clear cache group directly
vendor/bin/typo3 cache:flush --group=mpc_rss

# Clear everything (if needed)
vendor/bin/typo3 cache:flush
```

## Monitoring

- **Scheduler**: `System → Scheduler` → Check last execution time
- **Logs**: `System → Log` → Search for "RSS feed update"
- **Manual test**: Run `vendor/bin/typo3 mpcrss:updatefeeds` to see status

## Best Practices

1. ✅ Use scheduler for automatic updates (set and forget)
2. ✅ Leave "Clear cache" unchecked for normal operation
3. ✅ Monitor logs occasionally for failed feeds
4. ✅ Match cache lifetime to your content freshness needs
5. ❌ Don't clear RSS cache manually unless troubleshooting
