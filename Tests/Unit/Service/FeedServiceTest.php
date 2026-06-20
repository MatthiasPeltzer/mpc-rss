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
            'localhost hostname is blocked' => ['http://localhost/feed.xml', false],
            'localhost with port is blocked' => ['http://localhost:8080/feed.xml', false],
            '127.0.0.1 hostname is blocked' => ['http://127.0.0.1/feed.xml', false],
            'private network 10.x is blocked' => ['http://10.1.2.3/feed.xml', false],
            'private network 192.168.x is blocked' => ['http://192.168.1.1/feed.xml', false],
            'private network 172.16-31.x is blocked' => ['http://172.16.0.1/feed.xml', false],
            'link-local 169.254.x is blocked' => ['http://169.254.1.1/feed.xml', false],
            'TEST-NET 192.0.2.x is blocked' => ['http://192.0.2.1/feed.xml', false],
        ];
    }

    #[DataProvider('allowedFeedUrlProvider')]
    public function testIsAllowedFeedUrl(string $url, bool $expected): void
    {
        self::assertSame($expected, $this->invoke('isAllowedFeedUrl', $url));
    }

    public function testParseXmlSafelyDoesNotResolveExternalEntities(): void
    {
        $maliciousXml = '<?xml version="1.0"?>
            <!DOCTYPE rss [
                <!ENTITY xxe SYSTEM "file:///etc/passwd">
            ]>
            <rss><channel><title>&xxe;</title></channel></rss>';

        $result = $this->invoke('parseXmlSafely', $maliciousXml);

        // The external entity must never be resolved into the parsed document:
        // either parsing fails (null) or the title is empty.
        self::assertTrue($result === null || (string)$result->channel->title === '');
    }

    public function testParseXmlSafelyDoesNotExpandBillionLaughs(): void
    {
        $maliciousXml = '<?xml version="1.0"?>
            <!DOCTYPE lolz [
                <!ENTITY lol "lol">
                <!ENTITY lol2 "&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;">
                <!ENTITY lol3 "&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;">
            ]>
            <rss><channel><title>&lol3;</title></channel></rss>';

        $result = $this->invoke('parseXmlSafely', $maliciousXml);

        // Without LIBXML_NOENT entities are not expanded, so the title stays tiny.
        self::assertTrue($result === null || strlen((string)$result->channel->title) < 100);
    }

    public function testParseXmlSafelyRefusesNetworkEntities(): void
    {
        $maliciousXml = '<?xml version="1.0"?>
            <!DOCTYPE rss [
                <!ENTITY external SYSTEM "https://evil.example/malicious.dtd">
            ]>
            <rss><channel><title>&external;</title></channel></rss>';

        $result = $this->invoke('parseXmlSafely', $maliciousXml);

        self::assertTrue($result === null || (string)$result->channel->title === '');
    }

    public function testParseXmlSafelyParsesValidFeed(): void
    {
        $xml = '<?xml version="1.0"?><rss><channel><item><title>Hello</title></item></channel></rss>';

        $result = $this->invoke('parseXmlSafely', $xml);

        self::assertNotNull($result);
        self::assertSame('Hello', (string)$result->channel->item->title);
    }

    public function testWarmCacheRejectsExcessivelyLongUrl(): void
    {
        $longUrl = 'https://example.com/' . str_repeat('a', 2050);
        self::assertFalse($this->invoke('warmCache', $longUrl, 3600, []));
    }

    /**
     * @return array<string, array{0: string, 1: list<string>, 2: string}>
     */
    public static function curlResolveProvider(): array
    {
        return [
            'https uses default port 443' => ['https://example.com/feed', ['1.2.3.4'], 'example.com:443:1.2.3.4'],
            'http uses default port 80' => ['http://example.com/feed', ['1.2.3.4'], 'example.com:80:1.2.3.4'],
            'explicit port is respected' => ['https://example.com:8443/feed', ['1.2.3.4'], 'example.com:8443:1.2.3.4'],
            'multiple ips are comma-joined' => ['https://example.com/feed', ['1.2.3.4', '2606:4700::1111'], 'example.com:443:1.2.3.4,2606:4700::1111'],
        ];
    }

    /**
     * @param list<string> $ips
     */
    #[DataProvider('curlResolveProvider')]
    public function testBuildCurlResolveOption(string $url, array $ips, string $expectedEntry): void
    {
        if (!defined('CURLOPT_RESOLVE')) {
            self::markTestSkipped('ext-curl is not loaded, CURLOPT_RESOLVE is unavailable.');
        }

        $option = $this->invoke('buildCurlResolveOption', $url, $ips);

        self::assertSame([CURLOPT_RESOLVE => [$expectedEntry]], $option);
    }

    public function testBuildCurlResolveOptionReturnsEmptyWithoutCurl(): void
    {
        if (defined('CURLOPT_RESOLVE')) {
            self::markTestSkipped('ext-curl is loaded, the no-curl fallback cannot be exercised here.');
        }

        self::assertSame([], $this->invoke('buildCurlResolveOption', 'https://example.com/feed', ['1.2.3.4']));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: ?string}>
     */
    public static function redirectLocationProvider(): array
    {
        return [
            'absolute https kept' => ['https://a.example/feed', 'https://b.example/other', 'https://b.example/other'],
            'relative path resolves against current url' => ['https://a.example/news/feed', '/rss/latest', 'https://a.example/rss/latest'],
            'protocol-relative inherits scheme' => ['https://a.example/feed', '//b.example/x', 'https://b.example/x'],
            'non-http scheme is rejected' => ['https://a.example/feed', 'ftp://b.example/x', null],
            'javascript scheme is rejected' => ['https://a.example/feed', 'javascript:alert(1)', null],
        ];
    }

    #[DataProvider('redirectLocationProvider')]
    public function testResolveRedirectLocation(string $currentUrl, string $location, ?string $expected): void
    {
        self::assertSame($expected, $this->invoke('resolveRedirectLocation', $currentUrl, $location));
    }
}
