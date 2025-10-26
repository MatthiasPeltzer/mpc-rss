# Navigation Customization Based on Grouping Mode

The MPC RSS extension automatically customizes the frontend navigation (category filter menu) based on the selected **Grouping Mode**. This provides a more intuitive and contextual user experience.

## Automatic Customizations

### 1. **Dynamic Navigation Label**

The navigation automatically displays a contextual heading based on the grouping mode:

| Grouping Mode | English Label | German Label |
|---------------|---------------|--------------|
| **Category** | "Filter by Category" | "Nach Kategorie filtern" |
| **Source** | "Filter by Source" | "Nach Quelle filtern" |
| **Date** | "Filter by Date" | "Nach Datum filtern" |
| **None** | (No navigation shown) | (Keine Navigation) |

### 2. **Visual Icons**

Each mode gets a distinctive icon badge:

- **Category Mode** - Primary blue badge for topic-based filtering
- **Source Mode** - Gray badge for news outlet identification
- **Date Mode** - Info blue badge for time-based browsing

### 3. **CSS Styling**

Mode-specific CSS classes provide visual differentiation:

**Category Mode:**
- Left border accent on active items
- Emphasis on topic categorization

**Source Mode:**
- Bold font weight for source names
- Gray badges for neutral presentation

**Date Mode:**
- Centered text alignment
- Minimum width for time period consistency
- Info-colored badges

### 4. **Responsive Behavior**

On mobile devices (< 768px):
- Smaller font sizes and padding
- Reduced gaps between items
- Date mode loses minimum width for flexibility

## Customization Options

### Override CSS Styles

Create your own CSS file in your site package and add custom styles:

```css
/* Override category mode styling */
.rss-categories ul[data-grouping-mode="category"] .nav-link {
    border-left: 4px solid #ff6b6b; /* Custom color */
}

/* Change source mode icons */
.rss-categories ul[data-grouping-mode="source"] .nav-link .badge {
    background-color: #28a745 !important; /* Green for sources */
}

/* Customize date mode layout */
.rss-categories ul[data-grouping-mode="date"] .nav-link {
    min-width: 150px; /* Wider time period buttons */
}
```

### Disable Icons

If you prefer text-only navigation, hide the badges:

```css
.rss-categories .nav-link .badge {
    display: none;
}
```

### Change Icon Characters

Replace the emoji icons with your own in the template (`Resources/Private/Templates/Feed/List.html`):

```html
<f:case value="source">
    <span class="badge bg-secondary me-1">Globe</span> <!-- Text instead of icon -->
</f:case>
```

Or use Font Awesome icons:

```html
<f:case value="source">
    <span class="badge bg-secondary me-1"><i class="fas fa-newspaper"></i></span>
</f:case>
```

### Hide Navigation Label

If you prefer no heading above the navigation:

```css
.rss-categories h3 {
    display: none;
}
```

## Template Customization

The navigation is rendered in `Resources/Private/Templates/Feed/List.html`. You can override this template by:

1. **Setting Custom Template Path** in Site Settings:
   - Go to: **Site Management > Settings > MPC RSS View Paths**
   - Set **Template Root Path** to your custom path

2. **Copy and Modify** the template:
   ```
   EXT:your_sitepackage/Resources/Private/Templates/Feed/List.html
   ```

3. **Customize the Navigation Block:**
   ```html
   <f:if condition="{showFilter}">
       <nav class="rss-categories mb-3">
           <!-- Your custom navigation markup -->
       </nav>
   </f:if>
   ```

## Data Attributes

The navigation includes a `data-grouping-mode` attribute for JavaScript integration:

```javascript
// Detect current grouping mode
const nav = document.querySelector('.rss-categories ul');
const mode = nav.dataset.groupingMode;

if (mode === 'source') {
    // Add custom behavior for source mode
}
```

## Accessibility Features

The navigation includes ARIA attributes for screen readers:

- `role="navigation"` - Identifies the navigation landmark
- `aria-label="{navigationLabel}"` - Provides context (e.g., "Filter by Category")
- `title="{category}"` - Tooltips on each link

## Examples

### Category Mode Navigation
```
Filter by Category
Politics  Economy  Technology  Sports
```

### Source Mode Navigation
```
Filter by Source
BBC News  The Guardian  TechCrunch  Reuters
```

### Date Mode Navigation
```
Filter by Date
Today  Yesterday  This Week  This Month
```

### None Mode
*(No navigation displayed - unified timeline only)*

## Best Practices

1. **Consistency:** Keep the same grouping mode across similar content areas
2. **Icons:** Use icons that match your site's design language
3. **Labels:** Translate labels to match your site's language
4. **Mobile:** Test navigation on small screens - ensure touch targets are adequate
5. **Accessibility:** Maintain ARIA labels when customizing

## Related Documentation

- [Grouping Modes](GroupingModes.md) - Learn about different grouping strategies
- [Custom Templates](CustomTemplates.md) - Override templates for deeper customization
- [Site Settings](../README.md#configuration) - Configure default settings

