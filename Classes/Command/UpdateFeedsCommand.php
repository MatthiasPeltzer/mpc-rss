<?php

declare(strict_types=1);

namespace Mpc\MpcRss\Command;

use Mpc\MpcRss\Service\FeedService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Command to update RSS feeds in the background
 * 
 * This command fetches all configured RSS feeds and updates the cache.
 * It can be run manually via CLI or automatically via TYPO3 Scheduler.
 * 
 * Usage:
 *   vendor/bin/typo3 mpcrss:updatefeeds
 *   vendor/bin/typo3 mpcrss:updatefeeds --clear-cache
 */
class UpdateFeedsCommand extends Command
{
    public function __construct(
        private readonly FeedService $feedService,
        private readonly ConnectionPool $connectionPool
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Update RSS feeds and refresh cache')
            ->setHelp('Fetches all configured RSS feeds from the database and updates the cache. This ensures visitors always see fresh content without waiting for feed fetching.')
            ->addOption(
                'clear-cache',
                'c',
                InputOption::VALUE_NONE,
                'Clear all RSS feed caches before updating'
            )
            ->addOption(
                'cache-lifetime',
                'l',
                InputOption::VALUE_REQUIRED,
                'Cache lifetime in seconds (default: 600)',
                '600'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('MPC RSS Feed Updater');

        $clearCache = $input->getOption('clear-cache');
        $cacheLifetime = (int)$input->getOption('cache-lifetime');

        if ($clearCache) {
            $io->section('Clearing RSS feed cache...');
            $cacheManager = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class);
            $cache = $cacheManager->getCache('mpc_rss');
            $cache->flush();
            $io->success('Cache cleared successfully.');
        }

        // Fetch all feed URLs from the database
        $io->section('Fetching feed configurations from database...');
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_mpcrss_domain_model_feed');
        $feedRecords = $queryBuilder
            ->select('feed_url', 'source_name')
            ->from('tx_mpcrss_domain_model_feed')
            ->where(
                $queryBuilder->expr()->eq('hidden', 0),
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->neq('feed_url', $queryBuilder->createNamedParameter(''))
            )
            ->groupBy('feed_url')
            ->executeQuery()
            ->fetchAllAssociative();

        if (empty($feedRecords)) {
            $io->warning('No RSS feeds configured in the database.');
            return Command::SUCCESS;
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

        $io->text(sprintf('Found %d unique feed URL(s) to update.', count($urls)));
        $io->newLine();

        // Update each feed
        $io->section('Updating feeds...');
        $io->progressStart(count($urls));

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($urls as $url) {
            try {
                // Fetch the feed and update cache
                // Use maxItems=0 to fetch all items during background update
                $this->feedService->fetchGroupedByCategory(
                    [$url],
                    maxItems: 0, // Fetch all items
                    cacheLifetime: $cacheLifetime,
                    includeCategories: [],
                    excludeCategories: [],
                    sourceNames: $sourceNames,
                    groupingMode: 'category'
                );
                $successCount++;
            } catch (\Throwable $e) {
                $errorCount++;
                $errors[] = sprintf('Failed to update %s: %s', $url, $e->getMessage());
            }
            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->newLine();

        // Summary
        $io->section('Summary');
        $io->table(
            ['Status', 'Count'],
            [
                ['Successfully updated', $successCount],
                ['Errors', $errorCount],
                ['Total', count($urls)],
            ]
        );

        if (!empty($errors)) {
            $io->warning('Some feeds failed to update:');
            $io->listing($errors);
        }

        if ($errorCount > 0) {
            $io->warning(sprintf('Completed with %d error(s).', $errorCount));
            return Command::FAILURE;
        }

        $io->success('All RSS feeds updated successfully!');
        return Command::SUCCESS;
    }
}

