# Custom Templates

Override templates by pointing to your site package. TYPO3's fallback system means you only need to override the files you want to change.

## Setup

**Via Site Settings** (recommended):
Site Management > Your Site > Settings > MPC RSS View Paths.

**Via TypoScript:**

```typoscript
plugin.tx_mpcrss.view {
    templateRootPaths.30 = EXT:your_sitepackage/Resources/Private/Templates/
    partialRootPaths.30 = EXT:your_sitepackage/Resources/Private/Partials/
    layoutRootPaths.30 = EXT:your_sitepackage/Resources/Private/Layouts/
}
```

Expected file structure:

```
your_sitepackage/Resources/Private/
└── Templates/Feed/List.html
```

## Template variables

**Layout / grouping:**

| Variable | Type | Description |
|----------|------|-------------|
| `{grouped}` | array | Items keyed by group name |
| `{categories}` | array | All group names (for navigation) |
| `{activeCategory}` | string | Currently selected group |
| `{showFilter}` | bool | Whether to show navigation pills |
| `{groupingMode}` | string | `category`, `source`, `date`, or `none` |
| `{navigationLabel}` | string | Translated heading for the navigation |

**Pagination** (when enabled):

| Variable | Type | Description |
|----------|------|-------------|
| `{paginate}` | bool | Pagination enabled |
| `{pagination}` | array | `page`, `numPages`, `total`, `activeCategory` |
| `{pages}` | array | Page numbers for iteration |

**Feed item properties** (inside `<f:for each="{entries}" as="entry">`):

| Property | Description |
|----------|-------------|
| `{entry.title}` | Plain-text title |
| `{entry.description}` | Sanitized HTML description |
| `{entry.link}` | Article URL (http/https only) |
| `{entry.date}` | ISO 8601 date string |
| `{entry.image}` | Image URL or empty |
| `{entry.sourceName}` | Display name of the feed source |
| `{entry.categories}` | Array of RSS category strings |

## Example

```html
<f:layout name="Default" />
<f:section name="Main">
  <f:for each="{grouped}" as="items" key="category">
    <h2>{category}</h2>
    <f:for each="{items}" as="item">
      <article>
        <h3><a href="{item.link}" target="_blank" rel="noopener noreferrer">{item.title}</a></h3>
        <f:if condition="{item.date}">
          <time datetime="{item.date}"><f:format.date date="{item.date}" format="d.m.Y" /></time>
        </f:if>
        <p>
          <f:format.stripTags>
            <f:format.crop maxCharacters="200" append="…">{item.description}</f:format.crop>
          </f:format.stripTags>
        </p>
      </article>
    </f:for>
  </f:for>
</f:section>
```

> **Security:** Always use `f:format.stripTags` for descriptions -- never `f:format.html()`.
> Descriptions originate from external feeds; stripping tags in the template provides defense-in-depth.

Default template: `EXT:mpc_rss/Resources/Private/Templates/Feed/List.html`
