<?php

use Mpc\MpcRss\Preview\FeedPreviewRenderer;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

call_user_func(static function (): void {
    // Add custom fields for RSS plugin
    $tempColumns = [
        'tx_mpcrss_feeds' => [
            'exclude' => false,
            'label' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tt_content.tx_mpcrss_feeds',
            'description' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tt_content.tx_mpcrss_feeds.description',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_mpcrss_domain_model_feed',
                'foreign_field' => 'tt_content',
                'foreign_sortby' => 'sorting',
                'appearance' => [
                    'collapseAll' => true,
                    'expandSingle' => true,
                    'levelLinksPosition' => 'bottom',
                    'useSortable' => true,
                    'showPossibleLocalizationRecords' => false,
                    'showSynchronizationLink' => false,
                    'showAllLocalizationLink' => false,
                    'enabledControls' => [
                        'info' => true,
                        'new' => true,
                        'dragdrop' => true,
                        'sort' => true,
                        'hide' => true,
                        'delete' => true,
                        'localize' => false,
                    ],
                ],
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
                'minitems' => 0,
                'maxitems' => 99,
            ],
        ],
        'tx_mpcrss_default_category' => [
            'exclude' => false,
            'label' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tt_content.tx_mpcrss_default_category',
            'description' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tt_content.tx_mpcrss_default_category.description',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'default' => 'Politik',
                'placeholder' => 'Politik',
            ],
        ],
        'tx_mpcrss_grouping_mode' => [
            'exclude' => false,
            'label' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tt_content.tx_mpcrss_grouping_mode',
            'description' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tt_content.tx_mpcrss_grouping_mode.description',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tt_content.tx_mpcrss_grouping_mode.category', 'value' => 'category'],
                    ['label' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tt_content.tx_mpcrss_grouping_mode.source', 'value' => 'source'],
                    ['label' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tt_content.tx_mpcrss_grouping_mode.date', 'value' => 'date'],
                    ['label' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tt_content.tx_mpcrss_grouping_mode.none', 'value' => 'none'],
                ],
                'default' => 'category',
            ],
        ],
        'tx_mpcrss_include_categories' => [
            'exclude' => false,
            'label' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tt_content.tx_mpcrss_include_categories',
            'description' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tt_content.tx_mpcrss_include_categories.description',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'eval' => 'trim',
                'placeholder' => 'Politik, Wirtschaft',
            ],
        ],
        'tx_mpcrss_exclude_categories' => [
            'exclude' => false,
            'label' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tt_content.tx_mpcrss_exclude_categories',
            'description' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tt_content.tx_mpcrss_exclude_categories.description',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'eval' => 'trim',
                'placeholder' => 'Sport, Wetter',
            ],
        ],
        'tx_mpcrss_max_items' => [
            'exclude' => false,
            'label' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tt_content.tx_mpcrss_max_items',
            'description' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tt_content.tx_mpcrss_max_items.description',
            'config' => [
                'type' => 'number',
                'size' => 10,
                'default' => 9,
            ],
        ],
        'tx_mpcrss_cache_lifetime' => [
            'exclude' => false,
            'label' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tt_content.tx_mpcrss_cache_lifetime',
            'description' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tt_content.tx_mpcrss_cache_lifetime.description',
            'config' => [
                'type' => 'number',
                'size' => 10,
                'default' => 1800,
            ],
        ],
        'tx_mpcrss_show_filter' => [
            'exclude' => false,
            'label' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tt_content.tx_mpcrss_show_filter',
            'description' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tt_content.tx_mpcrss_show_filter.description',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'items' => [
                    [
                        'label' => '',
                        'invertStateDisplay' => false,
                    ],
                ],
                'default' => 1,
            ],
        ],
        'tx_mpcrss_paginate' => [
            'exclude' => false,
            'label' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tt_content.tx_mpcrss_paginate',
            'description' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tt_content.tx_mpcrss_paginate.description',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'items' => [
                    [
                        'label' => '',
                        'invertStateDisplay' => false,
                    ],
                ],
                'default' => 0,
            ],
        ],
        'tx_mpcrss_items_per_page' => [
            'exclude' => false,
            'label' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tt_content.tx_mpcrss_items_per_page',
            'description' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tt_content.tx_mpcrss_items_per_page.description',
            'config' => [
                'type' => 'number',
                'size' => 10,
                'default' => 10,
            ],
        ],
    ];

    ExtensionManagementUtility::addTCAcolumns('tt_content', $tempColumns);

    // Register plugin (works with configurePlugin in ext_localconf.php)
    ExtensionUtility::registerPlugin(
        'MpcRss',
        'Feed',
        'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:plugin.title',
        'mpc-rss-plugin',
        'plugins',
        'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:plugin.description'
    );

    $GLOBALS['TCA']['tt_content']['types']['mpcrss_feed'] = [
        'previewRenderer' => FeedPreviewRenderer::class,
        'showitem' => '
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
                --palette--;;general,
                --palette--;;headers,
                tx_mpcrss_feeds,
                tx_mpcrss_grouping_mode,
                tx_mpcrss_default_category,
                tx_mpcrss_include_categories,
                tx_mpcrss_exclude_categories,
                tx_mpcrss_max_items,
                tx_mpcrss_cache_lifetime,
                tx_mpcrss_show_filter,
                tx_mpcrss_paginate,
                tx_mpcrss_items_per_page,
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:appearance,
                --palette--;;frames,
                --palette--;;appearanceLinks,
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language,
                --palette--;;language,
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
                --palette--;;hidden,
                --palette--;;access,
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:categories,
                categories,
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:notes,
                rowDescription,
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:extended,
        ',
    ];

    // Register icon for the CType in list view
    $GLOBALS['TCA']['tt_content']['ctrl']['typeicon_classes']['mpcrss_feed'] = 'mpc-rss-plugin';
});


