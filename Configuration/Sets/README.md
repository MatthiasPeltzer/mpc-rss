# Site Set: MPC RSS

TYPO3 13+ Site Set that auto-includes the extension's TypoScript and PageTSconfig.

## Usage

Add to your site's `config/sites/<your-site>/config.yaml`:

```yaml
dependencies:
  - mpc/mpc-rss
```

Then clear caches: `vendor/bin/typo3 cache:flush`

## Override settings

```yaml
settings:
  plugin:
    tx_mpcrss:
      settings:
        maxItems: 15
        cacheLifetime: 600
        defaultCategory: 'News'
```

All settings are also configurable per content element in the backend.
