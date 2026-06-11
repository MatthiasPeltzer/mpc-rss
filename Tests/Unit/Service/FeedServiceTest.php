<?php

declare(strict_types=1);

namespace Mpc\MpcRss\Tests\Unit\Service;

use Mpc\MpcRss\Service\FeedService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the pure (network-free) logic of {@see FeedService}: URL and
 * HTML sanitization, the SSRF host guard, deduplication, grouping and source
 * detection. The service is instantiated without its constructor so the tested
 * methods can run without the TYPO3 cache or HTTP client.
 */
#[CoversClass(FeedService::class)]
final class FeedServiceTest extends TestCase
{
    private FeedService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = (new \ReflectionClass(FeedService::class))->newInstanceWithoutConstructor();
    }

    private function invoke(string $method, mixed ...$arguments): mixed
    {
        $reflection = new \ReflectionMethod($this->subject, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($this->subject, ...$arguments);
    }

    /**
     * @return array<string, array{0: ?string, 1: string}>
     */
    public static function sanitizeUrlProvider(): array
    {
        return [
            'http is kept' => ['http://example.com/feed', 'http://example.com/feed'],
            'https is kept' => ['https://example.com/a?b=c', 'https://example.com/a?b=c'],
            'javascript scheme is rejected' => ['javascript:alert(1)', ''],
            'ftp scheme is rejected' => ['ftp://example.com/file', ''],
            'scheme-relative url is rejected' => ['//example.com/feed', ''],
            'empty string stays empty' => ['', ''],
            'null stays empty' => [null, ''],
        ];
    }

    #[DataProvider('sanitizeUrlProvider')]
    public function testSanitizeUrl(?string $input, string $expected): void
    {
        self::assertSame($expected, $this->invoke('sanitizeUrl', $input));
    }

    public function testSanitizeHtmlStripsScriptTags(): void
    {
        $result = $this->invoke('sanitizeHtml', '<script>steal()</script><p>Hello</p>');
        self::assertStringNotContainsString('<script', $result);
        self::assertStringContainsString('<p>Hello</p>', $result);
    }

    public function testSanitizeHtmlRemovesAttributesFromAllowedTags(): void
    {
        self::assertSame('<p>Hi</p>', $this->invoke('sanitizeHtml', '<p onclick="x()" class="y">Hi</p>'));
    }

    public function testSanitizeHtmlKeepsHttpLinkButDropsOtherAttributes(): void
    {
        self::assertSame(
            '<a href="https://example.com">link</a>',
            $this->invoke('sanitizeHtml', '<a href="https://example.com" onclick="x()">link</a>'),
        );
    }

    public function testSanitizeHtmlStripsNonHttpHrefFromLinks(): void
    {
        self::assertSame('<a>x</a>', $this->invoke('sanitizeHtml', '<a href="javascript:alert(1)">x</a>'));
    }

    public function testSanitizeHtmlReturnsEmptyForEmptyInput(): void
    {
        self::assertSame('', $this->invoke('sanitizeHtml', ''));
    }

    public function testDeduplicateItemsRemovesDuplicateLinks(): void
    {
        $items = [
            ['link' => 'https://example.com/a', 'title' => 'First'],
            ['link' => 'https://example.com/a', 'title' => 'Duplicate'],
            ['link' => 'https://example.com/b', 'title' => 'Second'],
        ];

        $result = $this->invoke('deduplicateItems', $items);

        self::assertCount(2, $result);
        self::assertSame('First', $result[0]['title']);
        self::assertSame('Second', $result[1]['title']);
    }

    public function testDeduplicateItemsKeepsItemsWithoutLink(): void
    {
        $items = [
            ['link' => '', 'title' => 'No link 1'],
            ['link' => '', 'title' => 'No link 2'],
        ];

        $result = $this->invoke('deduplicateItems', $items);

        self::assertCount(2, $result);
    }

    public function testGroupItemsNoneReturnsSingleGroupSortedByDateDescending(): void
    {
        $items = [
            ['link' => 'https://example.com/old', 'date' => '2020-01-01T00:00:00+00:00'],
            ['link' => 'https://example.com/new', 'date' => '2024-01-01T00:00:00+00:00'],
        ];

        $result = $this->invoke('groupItems', $items, 'none', [], []);

        self::assertSame(['General'], array_keys($result));
        self::assertSame('https://example.com/new', $result['General'][0]['link']);
        self::assertSame('https://example.com/old', $result['General'][1]['link']);
    }

    public function testGroupItemsCategoryUsesItemCategories(): void
    {
        $items = [
            ['link' => 'https://example.com/1', 'categories' => ['Politik'], 'source' => '', 'sourceName' => ''],
            ['link' => 'https://example.com/2', 'categories' => ['Sport'], 'source' => '', 'sourceName' => ''],
        ];

        $result = $this->invoke('groupItems', $items, 'category', [], []);

        self::assertArrayHasKey('Politik', $result);
        self::assertArrayHasKey('Sport', $result);
    }

    public function testGroupItemsCategoryAppliesExcludeFilter(): void
    {
        $items = [
            ['link' => 'https://example.com/1', 'categories' => ['Politik'], 'source' => '', 'sourceName' => ''],
            ['link' => 'https://example.com/2', 'categories' => ['Sport'], 'source' => '', 'sourceName' => ''],
        ];

        $result = $this->invoke('groupItems', $items, 'category', [], ['sport']);

        self::assertArrayHasKey('Politik', $result);
        self::assertArrayNotHasKey('Sport', $result);
    }

    public function testResolveCategoryGroupKeyReturnsNullWhenExcluded(): void
    {
        $item = ['categories' => ['Sport'], 'source' => '', 'sourceName' => ''];

        self::assertNull($this->invoke('resolveCategoryGroupKey', $item, [], ['sport']));
    }

    public function testResolveCategoryGroupKeyReturnsNullWhenNotIncluded(): void
    {
        $item = ['categories' => ['Sport'], 'source' => '', 'sourceName' => ''];

        self::assertNull($this->invoke('resolveCategoryGroupKey', $item, ['politik'], []));
    }

    public function testResolveCategoryGroupKeyDetectsCategoryFromSourceUrl(): void
    {
        $item = ['categories' => [], 'source' => 'https://news.example.com/politik/article', 'sourceName' => ''];

        self::assertSame('Politik', $this->invoke('resolveCategoryGroupKey', $item, [], []));
    }

    /**
     * @return array<string, array{0: string, 1: array<string, string>, 2: string}>
     */
    public static function detectSourceNameProvider(): array
    {
        return [
            'strips www and capitalises host' => ['https://www.bbc.com/news', [], 'Bbc'],
            'explicit mapping wins' => ['https://www.bbc.com/news', ['https://www.bbc.com/news' => 'BBC News'], 'BBC News'],
            'fallback for url without host' => ['not-a-url', [], 'RSS'],
        ];
    }

    /**
     * @param array<string, string> $sourceNames
     */
    #[DataProvider('detectSourceNameProvider')]
    public function testDetectSourceName(string $url, array $sourceNames, string $expected): void
    {
        self::assertSame($expected, $this->invoke('detectSourceName', $url, $sourceNames));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function publicIpProvider(): array
    {
        return [
            'public ipv4' => ['8.8.8.8', true],
            'public ipv6' => ['2606:4700:4700::1111', true],
            'loopback ipv4' => ['127.0.0.1', false],
            'private 10/8' => ['10.0.0.1', false],
            'private 192.168/16' => ['192.168.1.1', false],
            'link-local metadata' => ['169.254.169.254', false],
            'loopback ipv6' => ['::1', false],
            'unique local ipv6' => ['fc00::1', false],
        ];
    }

    #[DataProvider('publicIpProvider')]
    public function testIsPublicIp(string $ip, bool $expected): void
    {
        self::assertSame($expected, $this->invoke('isPublicIp', $ip));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function allowedFeedUrlProvider(): array
    {
        return [
            'public ipv4 literal is allowed' => ['http://8.8.8.8/feed.xml', true],
            'private ipv4 literal is blocked' => ['http://10.0.0.1/feed.xml', false],
            'loopback literal is blocked' => ['https://127.0.0.1/feed.xml', false],
            'ipv6 loopback literal is blocked' => ['http://[::1]/feed.xml', false],
            'non-http scheme is blocked' => ['ftp://example.com/feed.xml', false],
            'javascript scheme is blocked' => ['javascript:alert(1)', false],
        ];
    }

    #[DataProvider('allowedFeedUrlProvider')]
    public function testIsAllowedFeedUrl(string $url, bool $expected): void
    {
        self::assertSame($expected, $this->invoke('isAllowedFeedUrl', $url));
    }
}
