<?php

declare(strict_types=1);

namespace Mpc\MpcRss\Tests\Functional\Controller;

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for {@see \Mpc\MpcRss\Controller\FeedController::listAction}.
 *
 * A real frontend request renders the RSS content element through the Extbase
 * plugin and Fluid. Feed payloads are pre-seeded into the mpc_rss cache so the
 * request performs no network I/O.
 */
final class FeedControllerTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['extbase', 'fluid', 'frontend'];

    protected array $testExtensionsToLoad = ['mpc/mpc-rss'];

    /**
     * A stable encryption key for the cHash that HashService computes for the
     * f:link.action filter links rendered by the list template.
     *
     * Note: TYPO3 still emits "Undefined $TYPO3_CONF_VARS" warnings from
     * HashService at PHP shutdown (frontend session persistence) once the
     * testing framework has reset globals. Those are printed after the run,
     * are not attributed to any test, and do not affect the result.
     */
    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'encryptionKey' => '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
        ],
        // Let the pagination test pass the tx_mpcrss_feed[page] plugin argument
        // without computing a cHash; an invalid/missing cHash then disables the
        // page cache instead of returning a 404.
        'FE' => [
            'cacheHash' => [
                'enforceValidation' => false,
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->setUpFrontendRootPage(1, ['EXT:mpc_rss/Tests/Functional/Fixtures/TypoScript/rendering.typoscript']);
        $this->writeSiteConfiguration();
    }

    private function writeSiteConfiguration(): void
    {
        $path = Environment::getConfigPath() . '/sites/mpcrss';
        GeneralUtility::mkdir_deep($path);
        GeneralUtility::writeFile(
            $path . '/config.yaml',
            "rootPageId: 1\n"
            . "base: 'http://localhost/'\n"
            . "languages:\n"
            . "  - languageId: 0\n"
            . "    title: English\n"
            . "    base: /\n"
            . "    locale: en_US.UTF-8\n",
        );
    }

    private function renderHomePage(): string
    {
        return $this->renderHomePageWithPluginArguments([]);
    }

    /**
     * @param array<string, int|string> $pluginArguments Extbase plugin arguments (tx_mpcrss_feed namespace)
     */
    private function renderHomePageWithPluginArguments(array $pluginArguments): string
    {
        $uri = 'http://localhost/';
        if ($pluginArguments !== []) {
            $uri .= '?' . http_build_query(['tx_mpcrss_feed' => $pluginArguments]);
        }
        $response = $this->executeFrontendSubRequest((new InternalRequest($uri))->withPageId(1));

        return (string)$response->getBody();
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function seedFeedCache(string $url, array $items): void
    {
        $this->get(CacheManager::class)->getCache('mpc_rss')->set(
            'feed_' . md5($url),
            $items,
            ['mpc_rss'],
            3600,
        );
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private static function makeItem(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Item',
            'description' => 'Description',
            'link' => 'https://seeded.example/article',
            'date' => '2026-06-20T10:00:00+00:00',
            'categories' => ['Politik'],
            'image' => '',
            'authors' => [],
            'source' => 'https://seeded.example/feed',
            'sourceName' => 'Seeded Source',
        ];
    }

    public function testRendersSeededFeedItems(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/content_with_feed.csv');

        $this->seedFeedCache('https://seeded.example/feed', [
            self::makeItem([
                'title' => 'Seeded Headline',
                'description' => 'Body text for the seeded item.',
            ]),
        ]);

        $html = $this->renderHomePage();

        self::assertStringContainsString('Seeded Headline', $html);
        self::assertStringContainsString('https://seeded.example/article', $html);
        // The category grouping key is rendered as a navigation/section label.
        self::assertStringContainsString('Politik', $html);
    }

    public function testRendersNoFeedsNoticeWhenContentElementHasNoFeeds(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/content_without_feed.csv');

        $html = $this->renderHomePage();

        self::assertStringContainsString('No RSS feeds configured', $html);
    }

    public function testPaginatesItemsWithinTheActiveCategory(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/content_paginate.csv');

        // Five items in the same category; descending dates keep "Item 1" newest.
        $items = [];
        for ($i = 1; $i <= 5; $i++) {
            $items[] = self::makeItem([
                'title' => 'Item ' . $i,
                'link' => 'https://page.example/article/' . $i,
                'date' => sprintf('2026-06-%02dT10:00:00+00:00', 20 - $i),
                'source' => 'https://page.example/feed',
                'sourceName' => 'Page Source',
            ]);
        }
        $this->seedFeedCache('https://page.example/feed', $items);

        // itemsPerPage = 2 -> page 2 holds the 3rd and 4th newest items.
        $html = $this->renderHomePageWithPluginArguments(['page' => 2]);

        self::assertStringContainsString('Item 3', $html);
        self::assertStringContainsString('Item 4', $html);
        self::assertStringNotContainsString('>Item 1<', $html);
        self::assertStringNotContainsString('>Item 5<', $html);
        self::assertStringContainsString('rss-pagination', $html);
    }

    public function testGroupsBySourceName(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/content_source.csv');

        $this->seedFeedCache('https://src.example/feed', [
            self::makeItem([
                'title' => 'Source Item',
                'source' => 'https://src.example/feed',
                'sourceName' => 'Source A',
                'categories' => [],
            ]),
        ]);

        $html = $this->renderHomePageWithPluginArguments([]);

        // The source name becomes the group heading (and the active group).
        self::assertStringContainsString('Source A', $html);
        self::assertStringContainsString('Source Item', $html);
    }

    public function testGroupsByDateAndTranslatesGeneratedKeys(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/content_date.csv');

        // A "now" timestamp lands in the generated "Today" group, which the
        // controller translates via LocalizationUtility.
        $this->seedFeedCache('https://date.example/feed', [
            self::makeItem([
                'title' => 'Fresh Item',
                'link' => 'https://date.example/article',
                'date' => (new \DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM),
                'source' => 'https://date.example/feed',
                'sourceName' => 'Date Source',
                'categories' => [],
            ]),
        ]);

        $html = $this->renderHomePageWithPluginArguments([]);

        self::assertStringContainsString('Today', $html);
        self::assertStringContainsString('Fresh Item', $html);
    }
}
