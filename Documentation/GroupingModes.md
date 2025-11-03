# Grouping Modes

Organize RSS feed items by category, source, date, or display as a unified timeline.

## Available Modes

### 1. Category (Default)
Groups items by RSS category tags (e.g., Politics, Technology, Business).

**Use when:** Users want to browse by topic/subject matter.

**Limitations:** Requires consistent RSS category tags across feeds.

### 2. Source (Recommended)
Groups items by feed source name (e.g., BBC News, TechCrunch, The Guardian).

**Use when:** Displaying multiple news sources with clear attribution.

**Advantages:** Always consistent, no fallback complexity.

### 3. Date
Groups items by time periods (Today, Yesterday, This Week, This Month).

**Use when:** Chronological browsing matters (news monitoring, archives).

### 4. None (Unified Timeline)
No grouping - all items sorted chronologically.

**Use when:** Social media-style feed or real-time monitoring.

## Comparison

| Mode | Best For | Consistency | Complexity |
|------|----------|-------------|------------|
| Category | Topic browsing | Variable | Medium |
| Source | Multi-source aggregators | Always | Low |
| Date | Archives, time-based | Always | Low |
| None | Real-time, mobile | Always | Lowest |

## Configuration

**Per Plugin:**
- Set "Grouping Mode" in plugin settings (Content Element → Plugin → MPC RSS Feed)

**Global Default:**
- Site Management → Settings → MPC RSS Plugin → Grouping Mode

**Recommendation:** Use "Source" mode for most cases - it's predictable and always works.

