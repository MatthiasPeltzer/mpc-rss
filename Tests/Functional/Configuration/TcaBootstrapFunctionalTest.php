<?php

declare(strict_types=1);

namespace Mpc\MpcRss\Tests\Functional\Configuration;

use Mpc\MpcRss\Tests\Support\MpcRssTcaManifest;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class TcaBootstrapFunctionalTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = MpcRssTcaManifest::FUNCTIONAL_TEST_EXTENSIONS;

    #[Test]
    public function customTablesAreRegisteredInTca(): void
    {
        foreach (MpcRssTcaManifest::CUSTOM_TABLES as $table) {
            self::assertArrayHasKey($table, $GLOBALS['TCA']);
            self::assertArrayHasKey('ctrl', $GLOBALS['TCA'][$table]);
            self::assertArrayHasKey('columns', $GLOBALS['TCA'][$table]);
            self::assertArrayHasKey('types', $GLOBALS['TCA'][$table]);
        }
    }

    #[Test]
    public function feedContentTypeIsRegistered(): void
    {
        foreach (MpcRssTcaManifest::C_TYPES as $cType) {
            self::assertArrayHasKey($cType, $GLOBALS['TCA']['tt_content']['types']);
        }
    }

    #[Test]
    public function contentTypeIconIsRegistered(): void
    {
        $typeIcons = $GLOBALS['TCA']['tt_content']['ctrl']['typeicon_classes'] ?? [];
        self::assertIsArray($typeIcons);

        foreach (MpcRssTcaManifest::C_TYPE_ICONS as $cType => $icon) {
            self::assertSame($icon, $typeIcons[$cType] ?? null);
        }
    }

    #[Test]
    public function tcaSchemaBuildsForMpcRssTables(): void
    {
        $factory = GeneralUtility::makeInstance(TcaSchemaFactory::class);

        foreach (MpcRssTcaManifest::SCHEMA_TABLES as $table) {
            self::assertTrue($factory->has($table));
            self::assertSame($table, $factory->get($table)->getName());
        }
    }

    #[Test]
    public function ttContentOverridesExposeMpcRssColumns(): void
    {
        foreach (MpcRssTcaManifest::OVERRIDDEN_CORE_TABLE_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                self::assertArrayHasKey($column, $GLOBALS['TCA'][$table]['columns']);
            }
        }
    }

    #[Test]
    public function previewRendererIsRegisteredOnFeedContentType(): void
    {
        self::assertSame(
            MpcRssTcaManifest::PREVIEW_RENDERER_CLASS,
            $GLOBALS['TCA']['tt_content']['types']['mpcrss_feed']['previewRenderer'] ?? null,
        );
    }
}
