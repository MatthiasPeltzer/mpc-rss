<?php

defined('TYPO3') or die();

use Mpc\MpcRss\Controller\FeedController;
use Mpc\MpcRss\Task\UpdateFeedsTask;
use Mpc\MpcRss\Task\UpdateFeedsTaskAdditionalFieldProvider;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

call_user_func(static function (): void {
    // Register cache early so the DI container can resolve @cache.mpc_rss
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['mpc_rss'] ??= [];
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['mpc_rss']['frontend'] ??= VariableFrontend::class;
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['mpc_rss']['backend'] ??= Typo3DatabaseBackend::class;

    ExtensionUtility::configurePlugin(
        extensionName: 'MpcRss',
        pluginName: 'Feed',
        controllerActions: [
            FeedController::class => 'list',
        ],
        nonCacheableControllerActions: [
            FeedController::class => 'list',
        ],
        pluginType: ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][UpdateFeedsTask::class] = [
        'extension' => 'mpc_rss',
        'title' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang.xlf:scheduler.task.title',
        'description' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang.xlf:scheduler.task.description',
        'additionalFields' => UpdateFeedsTaskAdditionalFieldProvider::class,
    ];
});
