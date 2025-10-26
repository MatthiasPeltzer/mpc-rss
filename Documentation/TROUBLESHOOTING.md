# Troubleshooting Guide

## Common Issues and Solutions

### 1. Many Items in "Uncategorized" / Few Items in "Politik"

#### Problem
German RSS feeds (SPIEGEL, ZEIT, taz) often don't include `<category>` tags in their RSS items, causing all items to fall into "Uncategorized".

#### Solution ✅ Fixed in v2.0.0

The extension now uses **smart category detection**:

1. **First:** Check RSS `<category>` tags
2. **Second:** Extract category from feed URL path
   - Example: `https://www.spiegel.de/politik/index.rss` → **Politik**
   - Example: `https://www.zeit.de/wirtschaft/index.xml` → **Wirtschaft**
3. **Third:** Use source name (SPIEGEL, taz, ZEIT)
4. **Last resort:** "Allgemein" (General)

#### Detected Categories

The extension automatically detects these categories from URLs:

| German | English | Detected From |
|--------|---------|---------------|
| Politik | Politics | `/politik/` |
| Wirtschaft | Economy | `/wirtschaft/` or `/economy/` |
| Kultur | Culture | `/kultur/` or `/culture/` |
| Sport | Sports | `/sport/` or `/sports/` |
| Wissen | Science | `/wissen/` or `/science/` |
| Digital | Technology | `/digital/` or `/technology/` |
| Gesellschaft | Society | `/gesellschaft/` |

#### Example

**Before:**
- Feed: `https://www.spiegel.de/schlagzeilen/index.rss`
- Result: All items → "Uncategorized"

**After:**
- Feed: `https://www.spiegel.de/schlagzeilen/index.rss`
- Result: Items → "SPIEGEL" (source name)

**Better:**
- Feed: `https://www.spiegel.de/politik/index.rss`
- Result: Items → "Politik" (extracted from URL)

### 2. Pagination Doesn't Work

#### Problem
When clicking pagination buttons, the category filter was lost, showing wrong items or breaking the page.

#### Solution ✅ Fixed in v2.0.0

**What was fixed:**

1. **Category state preservation**: Pagination links now properly pass `filterCategory` parameter
2. **Navigation stays visible**: All categories remain visible in navigation even when paginating
3. **Better pagination UI**: Added First (««), Previous (‹), Next (›), Last (»») buttons

#### How It Works Now

```
User clicks: Politik → Page 2
✓ URL: ?filterCategory=Politik&page=2
✓ Shows: Politik items, page 2
✓ Navigation: All categories still visible
```

### 3. Category Filters Break Pagination

#### Problem
Selecting a category and then paginating showed wrong content.

#### Solution ✅ Fixed in v2.0.0

The controller now:
1. Stores all categories BEFORE filtering
2. Applies pagination to the active category
3. Filters the output to selected category
4. Passes all categories to template for navigation

### 4. No Items Displayed

#### Symptoms
- Empty page
- "No RSS items available" message

#### Possible Causes & Solutions

##### A. No Feeds Configured
**Check:** Backend content element → "RSS Feeds" section  
**Solution:** Click "Create new" and add at least one feed URL

##### B. Invalid Feed URLs
**Check:** Test feed URL in browser  
**Solution:** 
- Verify URL is accessible
- Check for HTTPS/HTTP issues
- Try feed in RSS reader first

##### C. Cache Issues
**Check:** Extension cache might be stale  
**Solution:**
```bash
vendor/bin/typo3 cache:flush
```

##### D. Category Filters Too Restrictive
**Check:** "Include categories" field  
**Solution:** 
- Leave empty to show all categories
- Or verify category names match RSS categories

### 5. Feeds Not Updating

#### Problem
New items from RSS feeds don't appear.

#### Solution

Check cache lifetime setting:
- Backend: Content element → "Cache lifetime (seconds)"
- Default: 1800 seconds (30 minutes)
- Lower value = more frequent updates (but higher server load)

**Force refresh:**
```bash
# Clear RSS cache
vendor/bin/typo3 cache:flush mpc_rss

# Or clear all caches
vendor/bin/typo3 cache:flush
```

### 6. Category Names Not Translated

#### Problem
Categories like "Uncategorized" appear in English on German pages.

#### Solution ✅ Improved in v2.0.0

- "Uncategorized" replaced with "Allgemein"
- URL-detected categories use German names
- Source names preserved as-is (SPIEGEL, taz, ZEIT)

### 7. Images Not Displaying

#### Problem
RSS items have no images or broken images.

#### Causes

1. **Feed doesn't include images**
   - Many RSS feeds don't provide image URLs
   - Solution: This is expected behavior

2. **HTTPS/HTTP mixed content**
   - Feed provides HTTP images on HTTPS site
   - Solution: Browser blocks mixed content (expected)

3. **Image URL invalid**
   - Feed provides broken image URL
   - Solution: Nothing we can do, feed issue

### 8. Performance Issues / Slow Loading

#### Problem
Page loads slowly when RSS plugin is present.

#### Solutions

1. **Increase cache lifetime**
   ```
   Cache lifetime: 3600 (1 hour) or 7200 (2 hours)
   ```

2. **Reduce number of feeds**
   - Each feed = 1 HTTP request
   - Solution: Combine similar feeds

3. **Reduce max items**
   ```
   Max items per category: 5 or 10 instead of 50
   ```

4. **Use include/exclude categories**
   - Filter out unwanted categories
   - Reduces processing time

### 9. Database Errors

#### Error: Table 'tx_mpcrss_domain_model_feed' doesn't exist

**Solution:**
```bash
vendor/bin/typo3 extension:setup
```

Or via Install Tool:
1. Admin Tools → Maintenance
2. Analyze Database Structure
3. Apply changes

#### Error: Column 'sys_language_uid' not found

**Solution:** Run database updates (same as above)

### 10. Categories Appear in Wrong Order

#### Problem
Categories not sorted alphabetically or logically.

#### Solution

Categories are sorted alphabetically by default. To customize order:

**Option A: Use Include Categories (ordered)**
```
Include categories: Politik,Wirtschaft,Kultur,Sport
```
This forces the order.

**Option B: Rename categories**
```
01_Politik, 02_Wirtschaft, 03_Kultur
```

### 11. Pagination Shows Too Many/Few Pages

#### Problem
Pagination shows 50 pages or only 1 page.

#### Solutions

**Too many pages:**
```
Items per page: 20 (increase from 10)
```

**Too few pages:**
```
Max items per category: 50 (increase from 10)
Items per page: 10 (keep lower)
```

## Best Practices

### Feed Selection

✅ **Good:**
- `https://www.spiegel.de/politik/index.rss` (specific category)
- `https://www.zeit.de/wirtschaft/index.xml` (specific category)
- `https://taz.de/!p4608;rss/` (main feed)

❌ **Avoid:**
- Very large feeds (>100 items) without category filtering
- Unreliable/slow feeds
- Feeds without proper date/time information

### Cache Configuration

| Use Case | Cache Lifetime |
|----------|----------------|
| Breaking news | 300-600 seconds (5-10 min) |
| Daily news | 1800-3600 seconds (30-60 min) |
| Weekly content | 7200-14400 seconds (2-4 hours) |
| Static content | 86400 seconds (24 hours) |

### Category Filtering

**Use case: Show only Politik and Wirtschaft**
```
Include categories: Politik,Wirtschaft
Exclude categories: (empty)
```

**Use case: Show everything except Sport**
```
Include categories: (empty)
Exclude categories: Sport,Fußball,Basketball
```

## Debugging

### Enable Debug Mode

In `config/system/additional.php`:

```php
$GLOBALS['TYPO3_CONF_VARS']['BE']['debug'] = true;
$GLOBALS['TYPO3_CONF_VARS']['FE']['debug'] = true;
```

### Check Feed Structure

Test feed URL in browser or RSS reader:
```
https://www.spiegel.de/politik/index.rss
```

Look for:
- `<item>` elements
- `<category>` tags
- `<pubDate>` for sorting
- `<enclosure>` or `<media:content>` for images

### Check Database

```sql
-- List all feeds
SELECT uid, pid, tt_content, title, feed_url, hidden, deleted 
FROM tx_mpcrss_domain_model_feed;

-- Check content element
SELECT uid, pid, CType, list_type 
FROM tt_content 
WHERE list_type = 'mpcrss_feed';
```

## Getting Help

1. **Check this guide** for common issues
2. **Clear all caches** - solves 80% of issues
3. **Run database updates** - fixes structural issues
4. **Test feed URL directly** - verify feed is valid
5. **Check TYPO3 logs** - `var/log/typo3_*.log`

## Changelog of Fixes

### v2.0.1 (Current)
- ✅ Fixed: Smart category detection from feed URLs
- ✅ Fixed: Pagination preserves category filter
- ✅ Fixed: Category navigation stays visible when paginating
- ✅ Improved: Better pagination UI (First/Previous/Next/Last)
- ✅ Changed: "Uncategorized" → "Allgemein" for German sites

### v2.0.0
- ✅ Refactored to database-driven architecture
- ✅ Added multilanguage support (DE/EN)
- ✅ Removed XML FlexForms
- ✅ Added inline record editing

---

**Last updated:** 2025-01-26  
**Extension version:** 2.0.1  
**TYPO3 version:** 13.x

