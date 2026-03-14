<?php

declare(strict_types=1);

namespace Mpc\MpcRss\Task;

use Doctrine\DBAL\ParameterType;
use Mpc\MpcRss\Service\FeedService;
use TYPO3\CMS\Core\Cache\CacheManager;
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
    public int $cacheLifetime = 3600;
    public bool $clearCache = false;

    public function execute(): bool
    {
        try {
            $feedService = GeneralUtility::makeInstance(FeedService::class);

            if ($this->clearCache) {
                GeneralUtility::makeInstance(CacheManager::class)->getCache('mpc_rss')->flush();
            }

            $feedRecords = $this->fetchFeedRecords();

            $sourceNames = [];
            foreach ($feedRecords as $record) {
                if (!empty($record['source_name'])) {
                    $sourceNames[$record['feed_url']] = $record['source_name'];
                }
            }

            foreach ($feedRecords as $record) {
                try {
                    $feedService->warmCache($record['feed_url'], $this->cacheLifetime, $sourceNames);
                } catch (\Throwable $e) {
                    $this->logException(
                        $e instanceof \Exception ? $e : new \Exception($e->getMessage(), (int)$e->getCode(), $e)
                    );
                }
            }

            return true;
        } catch (\Throwable $e) {
            $this->logException(
                $e instanceof \Exception ? $e : new \Exception($e->getMessage(), (int)$e->getCode(), $e)
            );
            return false;
        }
    }

    /**
     * @return list<array{feed_url: string, source_name: string}>
     */
    private function fetchFeedRecords(): array
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_mpcrss_domain_model_feed');
        return $qb
            ->select('feed_url')
            ->addSelectLiteral(
                'MAX(' . $qb->quoteIdentifier('source_name') . ') AS ' . $qb->quoteIdentifier('source_name')
            )
            ->from('tx_mpcrss_domain_model_feed')
            ->where(
                $qb->expr()->eq('hidden', $qb->createNamedParameter(0, ParameterType::INTEGER)),
                $qb->expr()->eq('deleted', $qb->createNamedParameter(0, ParameterType::INTEGER)),
                $qb->expr()->neq('feed_url', $qb->createNamedParameter(''))
            )
            ->groupBy('feed_url')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function getAdditionalInformation(): string
    {
        return sprintf(
            'Cache Lifetime: %d seconds, Clear Cache: %s',
            $this->cacheLifetime,
            $this->clearCache ? 'Yes' : 'No',
        );
    }
}
