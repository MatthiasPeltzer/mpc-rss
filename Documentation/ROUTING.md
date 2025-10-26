# Speaking URLs / Route Enhancer Documentation

The mpc-rss extension now supports beautiful, SEO-friendly URLs through TYPO3's Route Enhancer system.

## 🎯 URL Examples

### Before (Ugly)
```
/links/rss-feeds?tx_mpcrss_feed%5Baction%5D=list&tx_mpcrss_feed%5Bcontroller%5D=Feed&tx_mpcrss_feed%5BfilterCategory%5D=Ausland&tx_mpcrss_feed%5Bpage%5D=1&cHash=eacffac92cf08081c5e609c2c56270c4
```

### After (Beautiful) ✅
```
/links/rss-feeds/ausland/page-1
```

## 📋 Available URL Patterns

### 1. Main Feed Page
```
/links/rss-feeds/
```
Shows all categories with default settings.

### 2. Specific Category
```
/links/rss-feeds/politik
/links/rss-feeds/wirtschaft
/links/rss-feeds/kultur
```
Shows only items from the selected category.

### 3. Pagination
```
/links/rss-feeds/page-2
/links/rss-feeds/page-3
```
Paginated view of all categories.

### 4. Category with Pagination
```
/links/rss-feeds/politik/page-2
/links/rss-feeds/wirtschaft/page-3
```
Paginated view of specific category.

## 🗺️ Category Slug Mapping

### German (Default)

| URL Slug | Actual Category |
|----------|----------------|
| `/allgemein` | Allgemein |
| `/amerika` | Amerika |
| `/arbeit` | Arbeit |
| `/asien` | Asien |
| `/ausland` | Ausland |
| `/deutschland` | Deutschland |
| `/digital` | Digital |
| `/europa` | Europa |
| `/familie` | Familie |
| `/geld` | Geld |
| `/geschichte` | Geschichte |
| `/gesellschaft` | Gesellschaft |
| `/gesundheit` | Gesundheit |
| `/inland` | Inland |
| `/kultur` | Kultur |
| `/news` | News |
| `/panorama` | Panorama |
| `/partnerschaft` | Partnerschaft |
| `/politik` | Politik |
| `/spiegel` | SPIEGEL |
| `/sport` | Sport |
| `/taz` | taz |
| `/unternehmen` | Unternehmen |
| `/wirtschaft` | Wirtschaft |
| `/wissenschaft` | Wissenschaft |
| `/wissen` | Wissen |
| `/wochenmarkt` | wochenmarkt |
| `/zeit` | ZEIT |
| `/zeitgeschehen` | Zeitgeschehen |

### English URLs

For English pages (`/en/...`), the URLs use English slugs:

| URL Slug | Actual Category |
|----------|----------------|
| `/general` | Allgemein |
| `/americas` | Amerika |
| `/work` | Arbeit |
| `/asia` | Asien |
| `/foreign` | Ausland |
| `/germany` | Deutschland |
| `/technology` | Digital |
| `/europe` | Europa |
| `/family` | Familie |
| `/money` | Geld |
| `/history` | Geschichte |
| `/society` | Gesellschaft |
| `/health` | Gesundheit |
| `/domestic` | Inland |
| `/culture` | Kultur |
| `/news` | News |
| `/panorama` | Panorama |
| `/partnership` | Partnerschaft |
| `/politics` | Politik |
| `/spiegel` | SPIEGEL |
| `/sports` | Sport |
| `/taz` | taz |
| `/business` | Unternehmen |
| `/economy` | Wirtschaft |
| `/science` | Wissenschaft |
| `/knowledge` | Wissen |
| `/market` | wochenmarkt |
| `/zeit` | ZEIT |
| `/current-affairs` | Zeitgeschehen |

## 🔧 Configuration

The route enhancer is configured in:
```
config/sites/[your-site]/config.yaml
```

### Key Settings

```yaml
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
    page: '\d+'  # Only numbers allowed
  aspects:
    category:
      type: StaticValueMapper
      map:
        politik: Politik
        wirtschaft: Wirtschaft
        # ... more mappings
    page:
      type: StaticRangeMapper
      start: '1'
      end: '100'  # Max 100 pages
```

## ➕ Adding New Categories

If you add new categories in your feeds, update the site configuration:

### Step 1: Edit config.yaml

```yaml
MpcRssFeed:
  aspects:
    category:
      type: StaticValueMapper
      map:
        # Add your new category here
        new-category: "New Category"
```

### Step 2: Add to both locales

```yaml
localeMap:
  - locale: default
    map:
      new-category: "New Category"
  - locale: en_.*
    map:
      new-category-en: "New Category"
```

### Step 3: Clear Caches

```bash
vendor/bin/typo3 cache:flush
```

## 🌐 Multilanguage Support

The route enhancer supports different URL slugs per language:

**German Page:**
```
/links/rss-feeds/politik
```

**English Page:**
```
/en/links/rss-feeds/politics
```

Both link to the same category "Politik", just with different URL slugs.

## 🎨 SEO Benefits

### Before
- ❌ Long, ugly URLs
- ❌ Query parameters everywhere
- ❌ Poor search engine indexing
- ❌ Hard to share/remember

### After
- ✅ Clean, readable URLs
- ✅ SEO-friendly structure
- ✅ Better search engine indexing
- ✅ Easy to share and remember
- ✅ Multilanguage support

## 🔍 How It Works

### URL Generation

When you click a category link:
```html
<f:link.action action="list" arguments="{filterCategory: 'Politik'}">
```

TYPO3 generates:
```
/links/rss-feeds/politik
```

Instead of:
```
/links/rss-feeds?tx_mpcrss_feed[filterCategory]=Politik&...
```

### URL Parsing

When a user visits `/links/rss-feeds/politik`:

1. TYPO3 Route Enhancer parses the URL
2. Finds the `politik` slug
3. Maps it to `filterCategory: "Politik"`
4. Calls `FeedController::listAction()` with the parameter
5. Displays Politik category

## 🛠️ Troubleshooting

### URLs still ugly after configuration

**Solution:**
```bash
# Clear ALL caches
vendor/bin/typo3 cache:flush

# Or via backend
Admin Tools → Maintenance → Flush all caches
```

### 404 Error on category pages

**Possible causes:**
1. Category name not in mapping
2. Typo in slug mapping
3. Caches not cleared

**Solution:**
1. Check `config.yaml` has the category
2. Verify spelling matches exactly
3. Clear caches

### Page shows wrong category

**Cause:** Slug mapped to wrong category

**Solution:**
```yaml
map:
  politik: Politik  # ← Check this mapping
```

### English pages use German slugs

**Cause:** Locale mapping not configured

**Solution:** Add `localeMap` section with `en_.*` locale

## 📊 Performance

Route enhancers do **not** impact performance:
- ✅ URLs cached by TYPO3
- ✅ No database queries for URL parsing
- ✅ Static mapping = instant lookup

## 🎯 Best Practices

### 1. Use lowercase slugs
```yaml
# Good
politik: Politik
wirtschaft: Wirtschaft

# Bad
Politik: Politik  # Don't use uppercase in slugs
```

### 2. Use hyphens for multi-word categories
```yaml
# Good
neue-kategorie: "Neue Kategorie"

# Bad
neue_kategorie: "Neue Kategorie"  # Underscores less common
```

### 3. Keep slugs short and meaningful
```yaml
# Good
tech: Digital
sci: Wissen

# Less ideal
technology-and-digital-stuff: Digital
```

### 4. Match your site's language
If your site is primarily German, use German slugs in the default map.

## 🔗 Example Full URLs

Based on page slug `/links/rss-feeds`:

```
# Main page
https://mpeltzer.ddev.docker/links/rss-feeds/

# Categories
https://mpeltzer.ddev.docker/links/rss-feeds/politik
https://mpeltzer.ddev.docker/links/rss-feeds/wirtschaft
https://mpeltzer.ddev.docker/links/rss-feeds/kultur

# With pagination
https://mpeltzer.ddev.docker/links/rss-feeds/politik/page-2
https://mpeltzer.ddev.docker/links/rss-feeds/wirtschaft/page-3

# English version
https://mpeltzer.ddev.docker/en/links/rss-feeds/politics
https://mpeltzer.ddev.docker/en/links/rss-feeds/economy
```

## 📝 Summary

✅ **Configured:** Route enhancer in site configuration  
✅ **Supported:** 17 default categories + source names  
✅ **Multilingual:** German and English slugs  
✅ **SEO-friendly:** Clean, readable URLs  
✅ **Pagination:** Works with speaking URLs  
✅ **Extendable:** Easy to add new categories  

---

**Last updated:** 2025-01-26  
**Extension version:** 2.0.1  
**TYPO3 version:** 13.x

