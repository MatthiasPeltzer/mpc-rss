<?php

declare(strict_types=1);

namespace Mpc\MpcRss\Tests\Support;

use Mpc\MpcRss\Preview\FeedPreviewRenderer;

/**
 * Single source of truth for mpc-rss TCA expectations used by configuration tests.
 */
final class MpcRssTcaManifest
{
    /**
     * @var list<string>
     */
    public const FUNCTIONAL_TEST_EXTENSIONS = ['mpc/mpc-rss'];

    /**
     * @var list<string>
     */
    public const CUSTOM_TABLES = [
        'tx_mpcrss_domain_model_feed',
    ];

    /**
     * @var list<string>
     */
    public const C_TYPES = [
        'mpcrss_feed',
    ];

    /**
     * @var array<string, string>
     */
    public const C_TYPE_ICONS = [
        'mpcrss_feed' => 'mpc-rss-plugin',
    ];

    /**
     * @var array<string, list<string>>
     */
    public const OVERRIDDEN_CORE_TABLE_COLUMNS = [
        'tt_content' => [
            'tx_mpcrss_feeds',
            'tx_mpcrss_grouping_mode',
            'tx_mpcrss_max_items',
        ],
    ];

    /**
     * @var list<string>
     */
    public const SCHEMA_TABLES = [
        'tt_content',
        'tx_mpcrss_domain_model_feed',
    ];

    public const PREVIEW_RENDERER_CLASS = FeedPreviewRenderer::class;
}
