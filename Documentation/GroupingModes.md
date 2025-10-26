# RSS Feed Grouping Modes

The MPC RSS extension offers **flexible grouping options** to organize feed items in different ways depending on your use case.

## Available Grouping Modes

### 1. **Group by Category** (Default)
```
Politics (12 items)
  - BBC: Brexit negotiations...
  - Guardian: Election results...
  
Technology (8 items)
  - TechCrunch: New AI model...
  - Wired: Smartphone review...
  
Business (5 items)
  - Reuters: Stock market update...
```

**When to use:**
- ✅ Topic-focused sites (e.g., news portals)
- ✅ When feeds have consistent RSS `<category>` tags
- ✅ Users want to browse by subject matter
- ✅ Multi-source aggregation by topic

**Pros:**
- Organizes by subject/topic
- Easy to find specific content types
- Good for filtered browsing

**Cons:**
- Categories can be inconsistent across feeds
- Items without categories get generic grouping
- Harder to see overall timeline

---

### 2. **Group by Source** (Recommended for most cases)
```
BBC News (15 items)
  - Latest headline 1...
  - Latest headline 2...
  
TechCrunch (10 items)
  - Startup funding news...
  - Product launch...
  
The Guardian (12 items)
  - Opinion piece...
```

**When to use:**
- ✅ **News aggregators** - Show distinct sources
- ✅ **Dashboard** - Quick source scanning
- ✅ **Multi-source comparison** - Same story from different outlets
- ✅ **Brand awareness** - Users care about source credibility

**Pros:**
- Always consistent (source name always available)
- Clear attribution
- Easy to scan specific sources
- No fallback complexity

**Cons:**
- Can't easily browse by topic
- Duplicate stories across sources

**Best for:** Most use cases! 🌟

---

### 3. **Group by Date** (Timeline View)
```
Today (8 items)
  - BBC: Latest news...
  - TechCrunch: Just posted...
  - Guardian: Breaking story...

Yesterday (15 items)
  - Reuters: Yesterday's news...
  - BBC: Previous update...

This Week (23 items)
  - Multiple sources...
```

**When to use:**
- ✅ News monitoring / staying current
- ✅ Chronological importance matters
- ✅ Time-sensitive content
- ✅ Archive/history browsing

**Pros:**
- Clear temporal organization
- Easy to see what's new
- Good for archives

**Cons:**
- No topic or source grouping
- Harder to find specific subjects

---

### 4. **None (Unified Timeline)**
```
[Latest item from any source - 5 min ago]
  TechCrunch: Breaking: New product launch...

[Second latest - 12 min ago]
  BBC: World news update...

[Third - 23 min ago]
  Guardian: Analysis piece...

[All mixed by date, no grouping]
```

**When to use:**
- ✅ **Social media style** - Like Twitter/Mastodon
- ✅ **Real-time monitoring** - Latest across all sources
- ✅ **Minimal interface** - Single scrolling feed
- ✅ **Mobile-first** - Continuous scroll

**Pros:**
- Simplest interface
- Pure chronological order
- See latest across all sources instantly
- No navigation needed

**Cons:**
- No organization
- Hard to find older items
- Can be overwhelming with many feeds

**Best for:** Breaking news, social media replacement, single-column mobile layouts

---

## Comparison Table

| Feature | Category | Source | Date | None |
|---------|----------|--------|------|------|
| **Organization** | Topic-based | Source-based | Time-based | Chronological |
| **Consistency** | Variable | Always | Always | Always |
| **Best for** | Subject browsing | Multi-source | Archives | Real-time |
| **Navigation** | By topic | By source | By time | Scroll |
| **Complexity** | Medium | Low | Low | Lowest |
| **Mobile-friendly** | ⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Discoverability** | High | High | Medium | Low |

## Use Case Examples

### News Portal
```
Grouping Mode: Category
Why: Users browse by Politics, Sports, Business
Filter: Show specific topics
```

### Corporate Dashboard
```
Grouping Mode: Source
Why: Monitor specific news outlets
Example: BBC, Reuters, Bloomberg separate
```

### Breaking News Monitor
```
Grouping Mode: None (Timeline)
Why: See latest immediately
Sort: Newest first across all sources
```

### Weekly Digest
```
Grouping Mode: Date
Why: Show what happened each day
Example: Monday's news, Tuesday's news, etc.
```

### Personal News App
```
Grouping Mode: None or Source
Why: Like a social media feed
Mobile-optimized: Infinite scroll
```

## Configuration

### Backend (Per Plugin)
```
Plugin Settings:
└── Grouping Mode: [Dropdown]
    ├── Category (Default)
    ├── Source (Recommended)
    ├── Date
    └── None (Unified Timeline)
```

### Site Settings (Global Default)
```
Site Management → Settings → MPC RSS Plugin
└── Grouping Mode: "source"
```

### When to Override

| Scenario | Use |
|----------|-----|
| Same grouping everywhere | Site Settings only |
| Different per page | Override in plugin |
| A/B testing | Different plugins, different modes |

## Implementation Notes

### Category Mode (Current Default)
- Uses RSS `<category>` tags
- Falls back to URL-based category detection
- Falls back to source name
- Finally "Allgemein" / "General"

### Source Mode (Proposed)
- Groups by `sourceName` field
- Always consistent
- Simplest implementation
- **Recommended as new default**

### Date Mode
- Groups items by date ranges
- Options: Today, Yesterday, This Week, This Month
- Within each range: sorted by time

### None Mode
- No grouping at all
- Pure array sorted by date descending
- Simplest template
- Best performance

## Migration Path

### Phase 1: Add Field (Current)
- Add `tx_mpcrss_grouping_mode` field
- Keep "category" as default
- No breaking changes

### Phase 2: Recommended Change
- Change default to "source" in next major version
- Provide migration notice
- More predictable behavior

### Phase 3: Template Optimization
- Create separate templates per mode
- Optimize for each use case
- Better performance

## Future Enhancements

### Possible additions:
1. **Custom Grouping** - User-defined groups
2. **Multi-level** - Group by source, then category
3. **Tag Cloud** - Visual category browsing
4. **AI Categories** - ML-based topic detection
5. **Saved Views** - User preferences per plugin

## Conclusion

**For most users, we recommend "Group by Source"** because:
- ✅ Always consistent
- ✅ Clear attribution
- ✅ No fallback complexity
- ✅ Predictable behavior
- ✅ Source credibility matters

The current "Category" default works but requires good RSS category tags. Consider changing the default to "source" in the next major version for better out-of-the-box experience.

