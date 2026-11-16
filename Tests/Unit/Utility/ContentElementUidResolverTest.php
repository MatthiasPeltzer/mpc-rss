<?php

declare(strict_types=1);

namespace Mpc\MpcRss\Tests\Unit\Utility;

use Mpc\MpcRss\Utility\ContentElementUidResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentElementUidResolver::class)]
final class ContentElementUidResolverTest extends TestCase
{
    public function testResolveReturnsUidWhenNoTranslationOverlay(): void
    {
        self::assertSame(2444, ContentElementUidResolver::resolve(['uid' => 2444]));
    }

    public function testResolvePrefersLocalizedUidWhenPresent(): void
    {
        self::assertSame(
            2445,
            ContentElementUidResolver::resolve([
                'uid' => 2444,
                '_LOCALIZED_UID' => 2445,
            ]),
        );
    }

    public function testResolveReturnsZeroForEmptyData(): void
    {
        self::assertSame(0, ContentElementUidResolver::resolve([]));
    }
}
