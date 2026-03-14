# Grouping Modes

| Mode | Groups by | Best for |
|------|-----------|----------|
| **Category** (default) | RSS category tags | Topic-based browsing |
| **Source** | Feed source name | Multi-source aggregation |
| **Date** | Time periods (Today, This Week, ...) | Chronological archives |
| **None** | No grouping -- unified timeline | Real-time / mobile feeds |

**Source** mode is the most predictable -- it always works regardless of whether feeds provide category tags.

## Configuration

**Per content element:** set *Grouping Mode* in the plugin fields.

**Global default:** Site Management > Settings > MPC RSS Plugin > Grouping Mode.

## Category mode details

When a feed item has no RSS category tag, the extension tries to detect one from the feed URL path (e.g. `/politik/` maps to "Politik"). If that fails, the source name or "General" is used as fallback.

Include/exclude filters (comma-separated, case-insensitive) let you limit which categories appear.
