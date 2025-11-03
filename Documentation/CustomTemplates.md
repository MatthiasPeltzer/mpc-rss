# Custom Templates & Navigation

Customize frontend display, navigation, and styling by overriding templates in your site package.

## Setting Up Custom Templates

### Method 1: Site Settings (Recommended)

**Site Management** → Your Site → **Settings** → **MPC RSS View Paths**:
- Template Root Path: `EXT:your_sitepackage/Resources/Private/Templates/`
- Partial Root Path: `EXT:your_sitepackage/Resources/Private/Partials/`
- Layout Root Path: `EXT:your_sitepackage/Resources/Private/Layouts/`

### Method 2: TypoScript

```typoscript
plugin.tx_mpcrss {
  view {
    templateRootPaths.30 = EXT:your_sitepackage/Resources/Private/Templates/
    partialRootPaths.30 = EXT:your_sitepackage/Resources/Private/Partials/
    layoutRootPaths.30 = EXT:your_sitepackage/Resources/Private/Layouts/
  }
}
```

**Folder structure:**
```
your_sitepackage/Resources/Private/
├── Templates/Feed/List.html
├── Partials/RssItem.html
└── Layouts/Default.html
```

TYPO3 uses fallback paths - if a template isn't found in your custom path, it falls back to the extension defaults.

## Navigation Customization

The category navigation automatically adapts to the grouping mode:

| Grouping Mode | Navigation Label | Shows |
|---------------|------------------|-------|
| Category | "Filter by Category" | Topic categories |
| Source | "Filter by Source" | Feed sources |
| Date | "Filter by Date" | Time periods |
| None | (hidden) | No navigation |

### Customizing Navigation Styles

Override CSS in your site package:

```css
/* Hide navigation labels */
.rss-categories h3 { display: none; }

/* Customize badges */
.rss-categories .badge {
    background-color: #28a745 !important;
}

/* Mode-specific styling */
.rss-categories ul[data-grouping-mode="source"] .nav-link {
    font-weight: bold;
}
```

### Disable Navigation

Hide the navigation completely in "None" grouping mode, or via CSS:
```css
.rss-categories { display: none; }
```

## Template Variables

### Available in List.html

- `{grouped}` - Items grouped by category/source/date
- `{categories}` - All available category names
- `{activeCategory}` - Currently selected filter
- `{showFilter}` - Boolean: show navigation
- `{paginate}` - Boolean: pagination enabled
- `{pagination}` - Pagination data
- `{settings}` - Plugin settings

### Feed Item Properties

- `{item.title}`, `{item.description}`, `{item.link}`, `{item.date}`
- `{item.categories}`, `{item.image}`, `{item.sourceName}`, `{item.source}`

## Example: Custom Item Template

```html
<html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers">
<f:layout name="Default" />
<f:section name="main">
    <div class="rss-feed">
        <f:for each="{grouped}" as="items" key="category">
            <div class="rss-category">
                <h2>{category}</h2>
                <f:for each="{items}" as="item">
                    <article class="rss-item">
                        <h3><a href="{item.link}">{item.title}</a></h3>
                        <div class="rss-meta">
                            <span>{item.sourceName}</span>
                            <time>{item.date -> f:format.date(format: 'd.m.Y')}</time>
                        </div>
                        <div class="rss-description">
                            {item.description -> f:format.html()}
                        </div>
                    </article>
                </f:for>
            </div>
        </f:for>
    </div>
</f:section>
</html>
```

## Reference

Default templates are in: `EXT:mpc_rss/Resources/Private/Templates/Feed/List.html`

Copy and modify incrementally. The fallback system ensures nothing breaks if you miss a template.

