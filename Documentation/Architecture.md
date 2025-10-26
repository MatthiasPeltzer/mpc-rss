# Architecture & Design Decisions

## Why No "Record Storage Page"?

The MPC RSS extension **deliberately excludes** the "Record Storage Page" (`pages`) and "Recursive" fields because of its unique architecture.

### Traditional Extbase Plugin Pattern

**Typical use case:**
```
Page Tree:
├── News Page (displays plugin)
└── Storage Folder (contains news records)
    ├── News Article 1
    ├── News Article 2
    └── News Article 3
```

In this pattern:
- Records live in a **separate storage folder**
- Plugin uses `pages` field to know **where to find records**
- Repository respects `storagePid` setting

### MPC RSS Plugin Architecture

**Our architecture:**
```
Page Tree:
└── RSS Feed Page (contains plugin + feeds)
    Content Element: RSS Plugin
    ├── Feed 1 (inline record)
    ├── Feed 2 (inline record)
    └── Feed 3 (inline record)
```

**Key differences:**

1. **Inline Records (IRRE)**
   - Feeds are stored **directly with the content element**
   - No separate storage location needed
   - Direct `tt_content` → `tx_mpcrss_domain_model_feed` relationship

2. **Repository Configuration**
   ```php
   // FeedRepository.php
   public function initializeObject(): void
   {
       $querySettings = $this->createQuery()->getQuerySettings();
       $querySettings->setRespectStoragePage(false);  // ← Disabled!
       $this->setDefaultQuerySettings($querySettings);
   }
   ```

3. **Direct Foreign Key**
   ```php
   // We query by content element UID, not by storage page
   public function findByContentElement(int $contentUid)
   {
       $query = $this->createQuery();
       $query->matching($query->equals('ttContent', $contentUid));
       return $query->execute();
   }
   ```

### Why This Is Better for RSS Feeds

| Aspect | Traditional Pattern | MPC RSS Pattern |
|--------|-------------------|----------------|
| **Setup Complexity** | Need separate storage folder | All-in-one content element |
| **Data Relationship** | Loose (page-based) | Tight (direct foreign key) |
| **Portability** | Must maintain folder structure | Copy content element = done |
| **User Experience** | Two-step process (folder + plugin) | One-step (add feeds in plugin) |
| **Multi-site** | Shared storage possible | Each plugin independent |

### Backend UX

**With storage page:**
```
Step 1: Create storage folder
Step 2: Create feed records in folder
Step 3: Create plugin on page
Step 4: Configure storage page in plugin
```

**Without storage page (our approach):**
```
Step 1: Add RSS plugin to page
Step 2: Click "Add Feed" button
        ↓ Feeds are immediately part of the plugin!
```

### Technical Implementation

**Hidden fields in TCA:**
```php
// Configuration/TCA/Overrides/tt_content.php
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist']['mpcrss_feed'] = 'pages,recursive';
```

**Inline configuration:**
```php
'tx_mpcrss_feeds' => [
    'config' => [
        'type' => 'inline',
        'foreign_table' => 'tx_mpcrss_domain_model_feed',
        'foreign_field' => 'tt_content',  // ← Direct relationship
        'foreign_sortby' => 'sorting',
    ],
],
```

**Database schema:**
```sql
CREATE TABLE tx_mpcrss_domain_model_feed (
    tt_content int(11) unsigned DEFAULT '0' NOT NULL,  -- ← Foreign key to content element
    pid int(11) DEFAULT '0' NOT NULL,                   -- Where record is stored (same as content element)
    -- ...
);
```

### When Would Storage Page Make Sense?

Storage pages are useful when:
- You want to **share records** across multiple plugins
- Records should be **managed independently** from display
- Multiple editors need **centralized record management**
- Records have complex relationships (e.g., News with categories, tags, authors)

**Examples:**
- News/Blog articles
- Events calendar
- Product catalog
- Employee directory

### Why RSS Feeds Don't Need This

RSS feeds in our plugin:
- Are **not shared** between plugins (each plugin has its own feeds)
- Don't need **independent management** (configured per plugin)
- Are **simple records** (just URL + title + source name)
- Don't have **complex relationships**
- Are **configuration**, not content

### Analogy

Think of RSS feeds like:
- **Content element settings** (e.g., image in a text+image element)
- **Not like**: News articles that multiple plugins display

## Other Architecture Decisions

### 1. No FlexForms

**Why:** Pure PHP TCA is more maintainable and type-safe than XML.

### 2. Inline Records Over Select Fields

**Why:** Better UX - "Add Feed" button vs dropdown of pre-created records.

### 3. Repository Disables Storage Page Respect

**Why:** Even though we hide the field, we defensively disable the feature in code.

### 4. Direct Database Query Fallback

```php
// FeedController.php - Fallback if Extbase fails
if ($feedCount === 0) {
    $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
        ->getQueryBuilderForTable('tx_mpcrss_domain_model_feed');
    $feedRecords = $queryBuilder
        ->where($queryBuilder->expr()->eq('tt_content', $contentUid))
        ->execute();
}
```

**Why:** Extra safety layer for edge cases.

## Conclusion

The "Record Storage Page" field is **intentionally hidden and disabled** because the MPC RSS extension uses an **inline record architecture** where feeds are configuration data directly attached to the content element, not independent content records stored in folders.

This design provides:
- Simpler setup
- Better user experience  
- More intuitive data model
- Better portability
- No configuration errors from wrong storage page

**Bottom line:** The storage page concept doesn't apply to this plugin's architecture, so we hide it to avoid confusion!

