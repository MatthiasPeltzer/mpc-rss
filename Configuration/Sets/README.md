# TYPO3 13 Site Sets Configuration

This directory contains the Site Set configuration for the MPC RSS extension, which is the modern TYPO3 13 way to include TypoScript and configuration.

## Structure

```
Configuration/Sets/MpcRss/
├── config.yaml          # Set metadata and dependencies
├── settings.typoscript  # TypoScript setup (replaces old setup.typoscript)
└── page.tsconfig        # Page TSconfig (Content Element Wizard)
```

## Set Name

**`mpc/mpc-rss`**

This identifier is used in your site configuration (`config/sites/mpc/config.yaml`).

## Usage

### Include in Site Configuration

Add to your `config/sites/mpc/config.yaml` under `dependencies`:

```yaml
dependencies:
  - mpc/mp-core
  - mpc/mp-core-form
  - mpc/mp-core-news
  - mpc/mp-core-container
  - mpc/mp-core-seo
  - mpc/mpc-sitepackage
  - mpc/mpc-rss  # ← Add this line
```

### Clear Caches

After adding the set, clear all caches:

```bash
bin/typo3 cache:flush
```

## What This Replaces

In TYPO3 < 13, you had to manually include static TypoScript templates via the Template module. With Site Sets, the TypoScript is automatically included when the set is added to your site configuration.

## Configuration

Default settings can be overridden in your site configuration:

```yaml
settings:
  plugin:
    tx_mpcrss:
      settings:
        maxItems: 15
        cacheLifetime: 600
        defaultCategory: 'News'
```

## Dependencies

The set automatically requires:
- `typo3/fluid-styled-content` - For content element rendering

## Related Files

- **TCA Configuration**: `Configuration/TCA/`
- **Domain Models**: `Classes/Domain/Model/`
- **Controllers**: `Classes/Controller/`
- **Templates**: `Resources/Private/Templates/`

