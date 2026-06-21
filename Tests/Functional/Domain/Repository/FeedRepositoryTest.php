<?php

declare(strict_types=1);

namespace Mpc\MpcRss\Tests\Functional\Domain\Repository;

use Mpc\MpcRss\Domain\Model\Feed;
use Mpc\MpcRss\Domain\Repository\FeedRepository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for {@see FeedRepository}: they exercise the real Extbase
 * query against a database, including the enable-field handling (hidden /
 * deleted) that pure unit tests cannot cover.
 */
final class FeedRepositoryTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['extbase', 'fluid'];

    protected array $testExtensionsToLoad = ['mpc/mpc-rss'];

    private FeedRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/feeds_for_repository.csv');
        $this->subject = $this->get(FeedRepository::class);
    }

    public function testFindByContentElementReturnsOnlyVisibleFeedsOfThatElement(): void
    {
        $result = $this->subject->findByContentElement(100);

        // Only the visible feed of content element 100 is returned; the hidden
        // and deleted rows are filtered by Extbase enable-field handling, and
        // the feed of content element 200 is out of scope.
        self::assertCount(1, $result);

        $feed = $result->getFirst();
        self::assertInstanceOf(Feed::class, $feed);
        self::assertSame('https://a.example/feed', $feed->getFeedUrl());
        self::assertSame('Source A', $feed->getSourceName());
        self::assertSame(100, $feed->getTtContent());
    }

    public function testFindByContentElementReturnsFeedOfOtherElement(): void
    {
        $result = $this->subject->findByContentElement(200);

        self::assertCount(1, $result);
        self::assertSame('https://b.example/feed', $result->getFirst()->getFeedUrl());
    }

    public function testFindByContentElementReturnsEmptyForUnknownElement(): void
    {
        self::assertCount(0, $this->subject->findByContentElement(999));
    }
}
