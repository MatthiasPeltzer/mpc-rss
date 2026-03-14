<?php

declare(strict_types=1);

namespace Mpc\MpcRss\Command;

use Doctrine\DBAL\ParameterType;
use Mpc\MpcRss\Service\FeedService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Command to update RSS feeds in the background
 *
 * Usage:
 *   vendor/bin/typo3 mpcrss:updatefeeds
 *   vendor/bin/typo3 mpcrss:updatefeeds --clear-cache
 */
class UpdateFeedsCommand extends Command
{
    public function __construct(
        private readonly FeedService $feedService,
        private readonly ConnectionPool $connectionPool,
        private readonly CacheManager $cacheManager,
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

        if ($input->getOption('clear-cache')) {
            $io->section('Clearing RSS feed cache...');
            $this->cacheManager->getCache('mpc_rss')->flush();
            $io->success('Cache cleared successfully.');
        }

        $cacheLifetime = (int)$input->getOption('cache-lifetime');

        $io->section('Fetching feed configurations from database...');
        $feedRecords = $this->fetchFeedRecords();

        if ($feedRecords === []) {
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

        $io->section('Updating feeds...');
        $io->progressStart(count($urls));

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($urls as $url) {
            try {
                $this->feedService->warmCache($url, $cacheLifetime, $sourceNames);
                $successCount++;
            } catch (\Throwable $e) {
                $errorCount++;
                $errors[] = sprintf('Failed to update %s: %s', $url, $e->getMessage());
            }
            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->newLine();

        $io->section('Summary');
        $io->table(
            ['Status', 'Count'],
            [
                ['Successfully updated', $successCount],
                ['Errors', $errorCount],
                ['Total', count($urls)],
            ]
        );

        if ($errors !== []) {
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

    /**
     * @return list<array{feed_url: string, source_name: string}>
     */
    private function fetchFeedRecords(): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_mpcrss_domain_model_feed');
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
}
