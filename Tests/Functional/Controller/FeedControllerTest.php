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
        $response = $this->executeFrontendSubRequest((new InternalRequest())->withPageId(1));

        return (string)$response->getBody();
    }

    public function testRendersSeededFeedItems(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/content_with_feed.csv');

        $this->get(CacheManager::class)->getCache('mpc_rss')->set(
            'feed_' . md5('https://seeded.example/feed'),
            [
                [
                    'title' => 'Seeded Headline',
                    'description' => 'Body text for the seeded item.',
                    'link' => 'https://seeded.example/article',
                    'date' => '2026-06-20T10:00:00+00:00',
                    'categories' => ['Politik'],
                    'image' => '',
                    'authors' => [],
                    'source' => 'https://seeded.example/feed',
                    'sourceName' => 'Seeded Source',
                ],
            ],
            ['mpc_rss'],
            3600,
        );

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
}
