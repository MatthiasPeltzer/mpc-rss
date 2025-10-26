<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tx_mpcrss_domain_model_feed',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'sortby' => 'sorting',
        'hideTable' => true,
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'translationSource' => 'l10n_source',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'searchFields' => 'title,feed_url,description',
        'iconfile' => 'EXT:mpc_rss/Resources/Public/Icons/Feed.svg',
    ],
    'types' => [
        '1' => [
            'showitem' => '
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
                    title, feed_url, source_name, description,
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language,
                    sys_language_uid, l10n_parent,
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
                    hidden,
            ',
        ],
    ],
    'columns' => [
        'sys_language_uid' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => [
                'type' => 'language',
            ],
        ],
        'l10n_parent' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.l18n_parent',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => '', 'value' => 0],
                ],
                'foreign_table' => 'tx_mpcrss_domain_model_feed',
                'foreign_table_where' => 'AND {#tx_mpcrss_domain_model_feed}.{#pid}=###CURRENT_PID### AND {#tx_mpcrss_domain_model_feed}.{#sys_language_uid} IN (-1,0)',
                'default' => 0,
            ],
        ],
        'l10n_source' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.enabled',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'items' => [
                    [
                        'label' => '',
                        'invertStateDisplay' => true,
                    ],
                ],
            ],
        ],
        'tt_content' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'title' => [
            'exclude' => false,
            'label' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tx_mpcrss_domain_model_feed.title',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'max' => 255,
                'eval' => 'trim,required',
            ],
        ],
        'feed_url' => [
            'exclude' => false,
            'label' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tx_mpcrss_domain_model_feed.feed_url',
            'description' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tx_mpcrss_domain_model_feed.feed_url.description',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'max' => 1024,
                'eval' => 'trim,required',
                'placeholder' => 'https://example.com/feed.rss',
            ],
        ],
            'source_name' => [
                'exclude' => false,
                'label' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tx_mpcrss_domain_model_feed.source_name',
                'description' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tx_mpcrss_domain_model_feed.source_name.description',
                'config' => [
                    'type' => 'input',
                    'size' => 30,
                    'max' => 100,
                    'eval' => 'trim',
                    'placeholder' => 'Example: BBC News, TechCrunch, etc.',
                ],
            ],
        'description' => [
            'exclude' => false,
            'label' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tx_mpcrss_domain_model_feed.description',
            'description' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:tx_mpcrss_domain_model_feed.description.description',
            'config' => [
                'type' => 'text',
                'cols' => 50,
                'rows' => 3,
                'eval' => 'trim',
            ],
        ],
    ],
];

