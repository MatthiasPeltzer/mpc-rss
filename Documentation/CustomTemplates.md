# Custom Templates for MPC RSS

The MPC RSS extension allows you to customize the frontend display by providing your own templates, partials, and layouts.

## Method 1: Via Site Settings (Recommended for TYPO3 13)

### Configure in Site Management Module

1. Go to **Site Management** → Your Site → **Settings**
2. Find the **"MPC RSS View Paths"** category
3. Configure your custom paths:
   - **Template Root Path**: `EXT:your_sitepackage/Resources/Private/Templates/`
   - **Partial Root Path**: `EXT:your_sitepackage/Resources/Private/Partials/`
   - **Layout Root Path**: `EXT:your_sitepackage/Resources/Private/Layouts/`
4. Save

### Example Folder Structure

```
your_sitepackage/
└── Resources/
    └── Private/
        ├── Templates/
        │   └── Feed/
        │       └── List.html          # Custom RSS list template
        ├── Partials/
        │   └── RssItem.html            # Custom item partial
        └── Layouts/
            └── Default.html            # Custom layout
```

### How It Works

TYPO3 uses a **fallback system** for view paths:

1. **Priority 20**: Your custom path (from site settings)
2. **Priority 10**: Default extension path (fallback)

If a template isn't found in your custom path, TYPO3 automatically falls back to the extension's default templates.

## Method 2: Via TypoScript

You can also override paths directly in TypoScript:

```typoscript
plugin.tx_mpcrss {
  view {
    templateRootPaths.30 = EXT:your_sitepackage/Resources/Private/Templates/
    partialRootPaths.30 = EXT:your_sitepackage/Resources/Private/Partials/
    layoutRootPaths.30 = EXT:your_sitepackage/Resources/Private/Layouts/
  }
}
```

**Note**: Higher numbers = higher priority (30 overrides 20 and 10)

## Method 3: Via Site Configuration YAML

Edit `config/sites/your_site/config.yaml`:

```yaml
settings:
  plugin:
    tx_mpcrss:
      view:
        templateRootPath: 'EXT:your_sitepackage/Resources/Private/Templates/'
        partialRootPath: 'EXT:your_sitepackage/Resources/Private/Partials/'
        layoutRootPath: 'EXT:your_sitepackage/Resources/Private/Layouts/'
```

## Customization Examples

### Custom RSS Item Display

Create `YourSitepackage/Resources/Private/Templates/Feed/List.html`:

```html
<html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers">
<f:layout name="Default" />

<f:section name="main">
    <div class="my-custom-rss-feed">
        <f:for each="{grouped}" as="items" key="category">
            <div class="rss-category">
                <h2>{category}</h2>
                <div class="rss-items-grid">
                    <f:for each="{items}" as="item">
                        <div class="rss-item-card">
                            <f:if condition="{item.image}">
                                <img src="{item.image}" alt="{item.title}" class="rss-item-image" />
                            </f:if>
                            <h3><a href="{item.link}" target="_blank">{item.title}</a></h3>
                            <div class="rss-meta">
                                <span class="rss-source">{item.sourceName}</span>
                                <f:if condition="{item.date}">
                                    <time datetime="{item.date}">
                                        <f:format.date format="d.m.Y H:i">{item.date}</f:format.date>
                                    </time>
                                </f:if>
                            </div>
                            <div class="rss-description">
                                <f:format.html>{item.description}</f:format.html>
                            </div>
                        </div>
                    </f:for>
                </div>
            </div>
        </f:for>
    </div>
</f:section>
</html>
```

### Custom CSS Styling

The original template structure is maintained, so you can also just override CSS:

```css
/* In your sitepackage CSS */
.rss-feed {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 2rem;
}

.rss-item {
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 1.5rem;
    transition: box-shadow 0.3s;
}

.rss-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
```

## Available Template Variables

### In List.html

- `{grouped}` - Array of feed items grouped by category
- `{categories}` - Array of all available category names
- `{activeCategory}` - Currently selected category
- `{showFilter}` - Boolean: show category navigation
- `{paginate}` - Boolean: pagination enabled
- `{pagination}` - Pagination data (if enabled)
- `{pages}` - Array of page numbers
- `{settings}` - Plugin settings

### Feed Item Structure

Each item in `{grouped}` contains:
- `{item.title}` - Item title
- `{item.description}` - HTML description
- `{item.link}` - External link to source
- `{item.date}` - ISO 8601 date string
- `{item.categories}` - Array of category names
- `{item.image}` - Image URL (if available)
- `{item.sourceName}` - Source name (e.g., "SPIEGEL", "taz")
- `{item.source}` - Feed URL

## Tips

1. **Start Small**: Copy the original `List.html` and modify incrementally
2. **Keep Fallbacks**: The multi-path system ensures nothing breaks
3. **Test Per Site**: Different sites can use different templates
4. **Version Control**: Keep custom templates in your sitepackage repository

## Need Help?

Check the default templates in:
`_packages/mpc-rss/Resources/Private/Templates/Feed/List.html`

These serve as reference for available variables and structure.

