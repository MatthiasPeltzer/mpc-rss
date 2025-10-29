<?php

defined('TYPO3') or die();

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

call_user_func(static function (): void {
    // Register cache if not already configured
    $cacheKey = 'mpc_rss';
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$cacheKey] ??= [];
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$cacheKey]['frontend'] ??= \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class;
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$cacheKey]['backend'] ??= \TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend::class;
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$cacheKey]['groups'] ??= ['mpc_rss']; // Custom group for isolated cache management

    ExtensionUtility::configurePlugin(
        extensionName: 'MpcRss',
        pluginName: 'Feed',
        controllerActions: [
            \Mpc\MpcRss\Controller\FeedController::class => 'list',
        ],
        nonCacheableControllerActions: [
            \Mpc\MpcRss\Controller\FeedController::class => 'list',
        ],
        pluginType: ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );

    // Register icons
    $iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);
    $iconRegistry->registerIcon(
        'mpc-rss-plugin',
        \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        ['source' => 'EXT:mpc_rss/Resources/Public/Icons/rss.svg']
    );
    $iconRegistry->registerIcon(
        'mpc-rss-feed',
        \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        ['source' => 'EXT:mpc_rss/Resources/Public/Icons/Feed.svg']
    );

    // Register Scheduler Task for automatic RSS feed updates
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\Mpc\MpcRss\Task\UpdateFeedsTask::class] = [
        'extension' => 'mpc_rss',
        'title' => 'Update RSS Feeds',
        'description' => 'Automatically fetch and update RSS feeds in the cache. This ensures visitors always see fresh content.',
        'additionalFields' => \Mpc\MpcRss\Task\UpdateFeedsTaskAdditionalFieldProvider::class,
    ];
});


