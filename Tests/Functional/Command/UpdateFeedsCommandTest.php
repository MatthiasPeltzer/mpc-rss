<?php

declare(strict_types=1);

namespace Mpc\MpcRss\Tests\Functional\Command;

use Mpc\MpcRss\Command\UpdateFeedsCommand;
use Mpc\MpcRss\Service\FeedService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for {@see UpdateFeedsCommand}. The command's database query
 * (fetchFeedRecords) runs against a real database. FeedService is final and
 * cannot be mocked, so a real instance is used with an injected stub cache
 * frontend: a cache hit makes warmCache() succeed, while an SSRF-blocked URL
 * makes it fail before any network access. No HTTP or DNS is performed.
 */
final class UpdateFeedsCommandTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['mpc/mpc-rss'];

    /**
     * Build a real FeedService whose cache lookups are answered by the given
     * map (cache identifier => value, missing keys behave as a cache miss).
     *
     * @param array<string, mixed> $valuesByIdentifier
     */
    private function feedServiceWithCache(array $valuesByIdentifier): FeedService
    {
        $cache = $this->createMock(FrontendInterface::class);
        $cache->method('get')->willReturnCallback(
            static fn (string $identifier): mixed => $valuesByIdentifier[$identifier] ?? false,
        );

        $service = (new \ReflectionClass(FeedService::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(FeedService::class, 'cache'))->setValue($service, $cache);

        return $service;
    }

    private static function cacheId(string $url): string
    {
        return 'feed_' . md5($url);
    }

    private function createCommandTester(FeedService $feedService): CommandTester
    {
        $command = new UpdateFeedsCommand(
            $feedService,
            $this->get(ConnectionPool::class),
            $this->get(CacheManager::class),
        );

        return new CommandTester($command);
    }

    public function testReportsSuccessWhenNoFeedsAreConfigured(): void
    {
        $tester = $this->createCommandTester($this->feedServiceWithCache([]));
        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('No RSS feeds configured', $tester->getDisplay());
    }

    public function testUpdatesUniqueFeedUrlsFromDatabase(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/feeds_for_command.csv');

        // Hidden, deleted and empty-url rows are excluded and the duplicate URL
        // is collapsed by the GROUP BY, leaving exactly two unique URLs. Both
        // are served from the (stubbed) cache, so warmCache() succeeds.
        $feedService = $this->feedServiceWithCache([
            self::cacheId('https://a.example/feed') => [],
            self::cacheId('https://b.example/feed') => [],
        ]);

        $tester = $this->createCommandTester($feedService);
        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Found 2 unique feed URL(s)', $tester->getDisplay());
        self::assertStringContainsString('All RSS feeds updated successfully', $tester->getDisplay());
    }

    public function testReturnsFailureWhenAFeedCannotBeWarmed(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/feeds_mixed.csv');

        // The good URL is a cache hit (success); the loopback URL is rejected by
        // the SSRF guard before any network access (failure).
        $feedService = $this->feedServiceWithCache([
            self::cacheId('https://good.example/feed') => [],
        ]);

        $tester = $this->createCommandTester($feedService);
        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Completed with 1 error(s)', $tester->getDisplay());
    }
}
