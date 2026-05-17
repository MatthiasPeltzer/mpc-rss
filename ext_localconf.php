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

    // Legacy scheduler task registration (TYPO3 13.4 + 14.x).
    //
    // Deprecated in TYPO3 14 by #98453 in favour of native task types defined
    // via TCA on the new `tx_scheduler_task` table (registered through
    // `Configuration/TCA/Overrides/scheduler_*.php` with
    // `ExtensionManagementUtility::addRecordType()` and a task class that
    // implements `getTaskParameters()` / `setTaskParameters()`). That
    // replacement was *introduced* in TYPO3 14.0 and does not exist on 13.4,
    // so as long as this extension supports `^13.4 || ^14.0` we keep the
    // legacy registration here. To migrate when 13.4 support is dropped:
    //   1. Move the registration to `Configuration/TCA/Overrides/scheduler_mpc_rss_update_feeds_task.php`.
    //   2. Convert `UpdateFeedsTask` to declare its parameters as protected
    //      properties + implement `setTaskParameters()` / `getTaskParameters()`.
    //   3. Delete `UpdateFeedsTaskAdditionalFieldProvider`.
    // @extensionScannerIgnoreLine
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][UpdateFeedsTask::class] = [
        'extension' => 'mpc_rss',
        'title' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang.xlf:scheduler.task.title',
        'description' => 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang.xlf:scheduler.task.description',
        'additionalFields' => UpdateFeedsTaskAdditionalFieldProvider::class,
    ];
});
