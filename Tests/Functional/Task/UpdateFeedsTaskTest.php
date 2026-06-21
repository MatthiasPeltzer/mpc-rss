<?php

declare(strict_types=1);

namespace Mpc\MpcRss\Tests\Functional\Task;

use Mpc\MpcRss\Task\UpdateFeedsTask;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for {@see \Mpc\MpcRss\Task\UpdateFeedsTask}.
 *
 * The task resolves a real FeedService via GeneralUtility::makeInstance, so the
 * shared mpc_rss cache is pre-seeded to drive warmCache() deterministically
 * without any network I/O.
 */
final class UpdateFeedsTaskTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['extbase', 'fluid', 'scheduler'];

    protected array $testExtensionsToLoad = ['mpc/mpc-rss'];

    private function cache(): FrontendInterface
    {
        return $this->get(CacheManager::class)->getCache('mpc_rss');
    }

    private static function cacheId(string $url): string
    {
        return 'feed_' . md5($url);
    }

    public function testExecuteReturnsTrueWhenAllVisibleFeedsAreCached(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/feeds_for_command.csv');

        // Unique, visible feed URLs from the fixture (hidden/deleted/empty excluded).
        $this->cache()->set(self::cacheId('https://a.example/feed'), [], ['mpc_rss'], 3600);
        $this->cache()->set(self::cacheId('https://b.example/feed'), [], ['mpc_rss'], 3600);

        $task = new UpdateFeedsTask();

        self::assertTrue($task->execute());
    }

    public function testExecuteReturnsFalseWhenAFeedCannotBeWarmed(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/feeds_blocked_only.csv');

        $task = new UpdateFeedsTask();

        // The only feed resolves to loopback and is refused by the SSRF guard.
        self::assertFalse($task->execute());
    }

    public function testClearCacheFlushesBeforeWarming(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/feeds_blocked_only.csv');

        $staleId = self::cacheId('https://stale.example/feed');
        $this->cache()->set($staleId, [self::makeItem()], ['mpc_rss'], 3600);
        self::assertTrue($this->cache()->has($staleId));

        $task = new UpdateFeedsTask();
        $task->clearCache = true;
        $result = $task->execute();

        // The blocked feed still fails the run, but the cache was flushed first.
        self::assertFalse($result);
        self::assertFalse($this->cache()->has($staleId));
    }

    public function testGetAdditionalInformationSummarisesSettings(): void
    {
        $task = new UpdateFeedsTask();
        $task->cacheLifetime = 7200;
        $task->clearCache = true;

        $info = $task->getAdditionalInformation();

        self::assertStringContainsString('7200', $info);
        self::assertStringContainsString('Yes', $info);
    }

    /**
     * @return array<string, mixed>
     */
    private static function makeItem(): array
    {
        return [
            'title' => 'Stale',
            'description' => '',
            'link' => 'https://stale.example/article',
            'date' => '2026-06-20T10:00:00+00:00',
            'categories' => ['Politik'],
            'image' => '',
            'authors' => [],
            'source' => 'https://stale.example/feed',
            'sourceName' => 'Stale',
        ];
    }
}
