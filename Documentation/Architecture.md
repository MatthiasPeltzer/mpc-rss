# Architecture & Design Decisions

## Why No "Record Storage Page"?

The extension uses **inline records (IRRE)** where feeds are stored directly with the content element, not in separate storage folders. This simplifies setup and improves UX.

**Traditional pattern:**
- Separate storage folder → Create records → Link to plugin
- 3-4 step process

**Our pattern:**
- Add plugin → Click "Add Feed" → Done
- 2 step process

### Technical Implementation

Feeds have a direct foreign key to `tt_content`:

```php
// TCA hides storage page fields
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist']['mpcrss_feed'] = 'pages,recursive';

// Repository disables storage page respect
$querySettings->setRespectStoragePage(false);
```

**Why this fits RSS feeds:**
- Feeds are configuration, not shared content
- Simple records (URL + metadata)
- Each plugin has its own feeds
- Better portability (copy content element = done)

**When storage pages make sense:**
- Shared records across multiple plugins
- Complex relationships (news with categories, tags)
- Centralized record management

RSS feeds don't need this complexity.

## Other Design Decisions

- **No FlexForms**: Pure PHP TCA is more maintainable than XML
- **Inline records**: Better UX than dropdowns of pre-created records
- **Database query fallback**: Extra safety layer if Extbase fails

## Result

- Simpler setup and better UX
- More intuitive data model
- Better portability
- No storage page configuration errors

