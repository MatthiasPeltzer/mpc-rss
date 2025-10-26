# Multilanguage Support Documentation

The mpc-rss extension now includes complete multilanguage support for both backend and frontend.

## 🌍 Supported Languages

- **English (en)** - Default language
- **German (de)** - Complete translation

Additional languages can be easily added by creating new XLF files.

## 📁 File Structure

```
Resources/Private/Language/
├── locallang_db.xlf           # Backend labels (English)
├── de.locallang_db.xlf        # Backend labels (German)
├── locallang.xlf              # Frontend labels (English)
└── de.locallang.xlf           # Frontend labels (German)
```

## 🔧 Backend Translation

### What's Translated

#### Plugin
- Plugin title and description in content element wizard
- All field labels and descriptions
- Help texts and placeholders

#### Feed Records
- Feed model labels
- All field labels (Title, URL, Source Name, Description)
- Field descriptions and help texts

#### Content Element Fields
- RSS Feeds field
- Category filtering options
- Pagination settings
- Cache configuration

### How It Works

All TCA labels use TYPO3's `LLL:` (Label Language Link) syntax:

```php
'label' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tt_content.tx_mpcrss_feeds'
```

TYPO3 automatically selects the correct language based on the backend user's language preference.

## 🎨 Frontend Translation

### What's Translated

- "No items" message
- Pagination labels (Page, Next, Previous, etc.)
- Category filter labels
- Time/date strings (Today, Yesterday, etc.)
- Item details (Source, Published, Read more)

### Usage in Templates

Templates use the `f:translate` ViewHelper:

```html
<f:translate key="LLL:EXT:mpc_rss/Resources/Private/Language/locallang.xlf:rss.no_items">
    Fallback text
</f:translate>
```

## 🗣️ Language-Aware Feed Records

Feed records support TYPO3's standard language/translation system:

### Database Fields
- `sys_language_uid` - Current language
- `l10n_parent` - Parent record (original language)
- `l10n_source` - Translation source
- `l10n_diffsource` - Difference source for comparison

### Creating Translations

1. Create a feed in the default language
2. In the backend, use the "Translate" button
3. Select target language
4. Modify the feed details for that language

### Use Cases

**Different feeds per language:**
- German page → German news feeds (taz, SPIEGEL)
- English page → English news feeds (BBC, CNN)

**Same feeds, different labels:**
- Feed title translated
- Source name localized
- Description in native language

## 📋 Translation Keys Reference

### Backend (locallang_db.xlf)

#### Plugin
- `plugin.title` - Plugin title
- `plugin.description` - Plugin description

#### Feed Model
- `tx_mpcrss_domain_model_feed` - Table title
- `tx_mpcrss_domain_model_feed.title` - Feed title field
- `tx_mpcrss_domain_model_feed.feed_url` - Feed URL field
- `tx_mpcrss_domain_model_feed.source_name` - Source name field
- `tx_mpcrss_domain_model_feed.description` - Description field

#### Content Element
- `tt_content.tx_mpcrss_feeds` - RSS Feeds field
- `tt_content.tx_mpcrss_default_category` - Default category
- `tt_content.tx_mpcrss_include_categories` - Include categories
- `tt_content.tx_mpcrss_exclude_categories` - Exclude categories
- `tt_content.tx_mpcrss_max_items` - Max items
- `tt_content.tx_mpcrss_cache_lifetime` - Cache lifetime
- `tt_content.tx_mpcrss_show_filter` - Show filter
- `tt_content.tx_mpcrss_paginate` - Enable pagination
- `tt_content.tx_mpcrss_items_per_page` - Items per page

### Frontend (locallang.xlf)

#### General
- `rss.title` - RSS Feed title
- `rss.no_feeds` - No feeds configured
- `rss.no_items` - No items found
- `rss.loading` - Loading message
- `rss.error` - Error message

#### Category Filter
- `filter.all_categories` - All categories
- `filter.show_category` - Show category
- `filter.filter_by` - Filter by

#### Item Details
- `item.read_more` - Read more link
- `item.source` - Source label
- `item.published` - Published label
- `item.categories` - Categories label
- `item.no_description` - No description

#### Pagination
- `pagination.page` - Page
- `pagination.of` - of (as in "Page 1 of 5")
- `pagination.previous` - Previous
- `pagination.next` - Next
- `pagination.first` - First
- `pagination.last` - Last
- `pagination.items_total` - items total

#### Time/Date
- `time.just_now` - just now
- `time.minutes_ago` - X minutes ago
- `time.hours_ago` - X hours ago
- `time.days_ago` - X days ago
- `time.today` - Today
- `time.yesterday` - Yesterday

## ➕ Adding New Languages

### Step 1: Create Backend Translation

Create `Resources/Private/Language/[languagecode].locallang_db.xlf`:

```xml
<?xml version="1.0" encoding="utf-8" standalone="yes" ?>
<xliff version="1.0">
    <file source-language="en" target-language="fr" datatype="plaintext" 
          original="EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf" 
          date="2025-01-26T12:00:00Z" product-name="mpc_rss">
        <header/>
        <body>
            <trans-unit id="plugin.title">
                <source>MPC RSS Feed</source>
                <target>Flux RSS MPC</target>
            </trans-unit>
            <!-- Add more translations -->
        </body>
    </file>
</xliff>
```

### Step 2: Create Frontend Translation

Create `Resources/Private/Language/[languagecode].locallang.xlf` with the same structure.

### Step 3: Clear Caches

```bash
vendor/bin/typo3 cache:flush
```

## 🔄 Language Switching

### Frontend

The extension automatically uses the current page language (`sys_language_uid`). Feeds are fetched based on the language-specific records.

### Backend

Backend labels automatically adapt to the backend user's language preference set in their user profile.

## 🎯 Best Practices

### For Editors

1. **Create default language first** - Always create feeds in the default language
2. **Translate consistently** - Use the "Translate" button for proper language relations
3. **Test both languages** - Check frontend in all languages after setup

### For Developers

1. **Never hardcode strings** - Always use translation keys
2. **Provide fallbacks** - Include English text in `f:translate` tags
3. **Document new keys** - Add any new translation keys to this guide

## 🐛 Troubleshooting

### Backend shows English despite German setting

**Solution:** Clear all caches and reload the backend.

```bash
vendor/bin/typo3 cache:flush
```

### Frontend translations not working

**Solution:** 
1. Check that language files exist
2. Verify XLF syntax is correct
3. Clear frontend cache
4. Check page language (`sys_language_uid`)

### Feed translations not visible

**Solution:**
1. Ensure feeds have correct `sys_language_uid`
2. Check `l10n_parent` is set correctly
3. Run database updates if fields are missing

```bash
vendor/bin/typo3 extension:setup
```

## 📊 Database Schema

The `tx_mpcrss_domain_model_feed` table includes these language fields:

```sql
sys_language_uid int(11) DEFAULT '0' NOT NULL,
l10n_parent int(11) DEFAULT '0' NOT NULL,
l10n_source int(11) DEFAULT '0' NOT NULL,
l10n_diffsource mediumblob,
```

## 🚀 Implementation Details

### Domain Model

The `Feed` model includes language properties:

```php
protected int $sysLanguageUid = 0;
protected int $l10nParent = 0;
```

### TCA Configuration

Language support is configured in the TCA ctrl section:

```php
'languageField' => 'sys_language_uid',
'transOrigPointerField' => 'l10n_parent',
'transOrigDiffSourceField' => 'l10n_diffsource',
'translationSource' => 'l10n_source',
```

## 🌐 Example: Setting Up Bilingual Site

### Scenario: German/English site with different feeds

#### Step 1: Create German Feeds (Default)
1. Feed 1: SPIEGEL - Politik
   - URL: `https://www.spiegel.de/politik/index.rss`
   - Source: SPIEGEL

2. Feed 2: taz - Alle
   - URL: `https://taz.de/!p4608;rss/`
   - Source: taz

#### Step 2: Create English Translations
1. Translate Feed 1:
   - Title: "SPIEGEL - Politics"
   - URL: (could stay the same or change to English feed)
   - Source: SPIEGEL

2. Translate Feed 2:
   - Title: "taz - All"
   - URL: (could stay the same or change to English feed)
   - Source: taz

#### Result
- German page shows German feeds with German labels
- English page shows same or different feeds with English labels

---

**Last updated:** 2025-01-26  
**Extension version:** 2.0.0  
**TYPO3 version:** 13.x

