<?php

declare(strict_types=1);

namespace Mpc\MpcRss\Task;

use Doctrine\DBAL\ParameterType;
use Mpc\MpcRss\Service\FeedService;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Scheduler Task to automatically update RSS feeds
 * 
 * This task can be configured in the TYPO3 Scheduler module to run at regular intervals.
 * It fetches all configured RSS feeds and updates the cache, ensuring visitors always
 * see fresh content without waiting for feed fetching.
 */
class UpdateFeedsTask extends AbstractTask
{
    /**
     * Cache lifetime in seconds (default: 3600 = 1 hour)
     */
    public int $cacheLifetime = 3600;

    /**
     * Whether to clear the cache before updating
     */
    public bool $clearCache = false;

    /**
     * Execute the task
     * 
     * @return bool Returns true on successful execution, false on error
     */
    public function execute(): bool
    {
        try {
            $feedService = GeneralUtility::makeInstance(FeedService::class);
            $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

            // Clear cache if requested
            if ($this->clearCache) {
                $cacheManager = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class);
                $cache = $cacheManager->getCache('mpc_rss');
                $cache->flush();
            }

            // Fetch all feed URLs from the database
            $queryBuilder = $connectionPool->getQueryBuilderForTable('tx_mpcrss_domain_model_feed');
            $feedRecords = $queryBuilder
                ->select('feed_url', 'source_name')
                ->from('tx_mpcrss_domain_model_feed')
                ->where(
                    $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
                    $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
                    $queryBuilder->expr()->neq('feed_url', $queryBuilder->createNamedParameter(''))
                )
                ->groupBy('feed_url')
                ->executeQuery()
                ->fetchAllAssociative();

            if (empty($feedRecords)) {
                // No feeds configured, but this is not an error
                return true;
            }

            $urls = [];
            $sourceNames = [];
            foreach ($feedRecords as $record) {
                $url = $record['feed_url'];
                $urls[] = $url;
                if (!empty($record['source_name'])) {
                    $sourceNames[$url] = $record['source_name'];
                }
            }

            // Update each feed
            foreach ($urls as $url) {
                try {
                    // Fetch the feed and update cache
                    // Use maxItems=0 to fetch all items during background update
                    $feedService->fetchGroupedByCategory(
                        [$url],
                        maxItems: 0, // Fetch all items
                        cacheLifetime: $this->cacheLifetime,
                        includeCategories: [],
                        excludeCategories: [],
                        sourceNames: $sourceNames,
                        groupingMode: 'category'
                    );
                } catch (\Throwable $e) {
                    // Log error but continue with other feeds
                    // logException only accepts Exception, not Error types
                    if ($e instanceof \Exception) {
                        $this->logException($e);
                    } else {
                        // Wrap Error types in Exception for logging
                        $this->logException(new \Exception($e->getMessage(), (int)$e->getCode(), $e));
                    }
                }
            }

            return true;
        } catch (\Throwable $e) {
            // Log the exception using parent class method
            // logException only accepts Exception, not Error types
            if ($e instanceof \Exception) {
                $this->logException($e);
            } else {
                // Wrap Error types in Exception for logging
                $this->logException(new \Exception($e->getMessage(), (int)$e->getCode(), $e));
            }
            return false;
        }
    }

    /**
     * Get additional information for the task list
     *
     * @return string Information to display
     */
    public function getAdditionalInformation(): string
    {
        $info = [];
        $info[] = sprintf('Cache Lifetime: %d seconds', $this->cacheLifetime);
        $info[] = sprintf('Clear Cache: %s', $this->clearCache ? 'Yes' : 'No');

        return implode(', ', $info);
    }
}

