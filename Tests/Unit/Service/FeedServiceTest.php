<?php

declare(strict_types=1);

namespace Mpc\MpcRss\Tests\Unit\Service;

use GuzzleHttp\Psr7\Utils;
use Mpc\MpcRss\Service\FeedService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

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
        // Since PHP 8.1 reflection ignores method visibility, so no
        // setAccessible() call is needed (it is a deprecated no-op on 8.5+).
        return (new \ReflectionMethod($this->subject, $method))->invoke($this->subject, ...$arguments);
    }

    /**
     * Inject a cache frontend so the (otherwise network-bound) fetch methods can
     * be exercised from a cache hit alone. Setting the private `cache` property
     * short-circuits getCache(), so the CacheManager is never touched.
     *
     * @param array<string, mixed> $valuesByIdentifier feed_<md5(url)> => cached value
     */
    private function injectCacheReturning(array $valuesByIdentifier): void
    {
        $cache = $this->createMock(FrontendInterface::class);
        $cache->method('get')->willReturnCallback(
            static fn(string $identifier): mixed => $valuesByIdentifier[$identifier] ?? false,
        );

        (new \ReflectionProperty(FeedService::class, 'cache'))->setValue($this->subject, $cache);
    }

    private static function cacheId(string $url): string
    {
        return 'feed_' . md5($url);
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

    /**
     * Build an `<item>` element with the media and atom namespaces declared so
     * the namespace-sensitive image extraction can be exercised in isolation.
     */
    private function makeItem(string $innerXml): \SimpleXMLElement
    {
        $xml = '<?xml version="1.0"?>'
            . '<rss xmlns:media="http://search.yahoo.com/mrss/" xmlns:atom="http://www.w3.org/2005/Atom">'
            . '<channel><item>' . $innerXml . '</item></channel></rss>';

        return (new \SimpleXMLElement($xml))->channel->item[0];
    }

    /**
     * Regression test for the SimpleXML namespace gotcha: the `url` attribute on
     * a `<media:content>` reached via children($ns) lives in *no* namespace, so
     * it must be read with attributes(), not `$node['url']`.
     */
    public function testExtractImageUrlReadsMediaContentUrl(): void
    {
        $item = $this->makeItem('<media:content url="https://example.com/pic.jpg" medium="image" type="image/jpeg"/>');

        self::assertSame('https://example.com/pic.jpg', $this->invoke('extractImageUrl', $item, ''));
    }

    public function testExtractImageUrlFallsBackToMediaThumbnail(): void
    {
        $item = $this->makeItem('<media:thumbnail url="https://example.com/thumb.jpg"/>');

        self::assertSame('https://example.com/thumb.jpg', $this->invoke('extractImageUrl', $item, ''));
    }

    public function testExtractImageUrlPrefersEnclosureOverMediaContent(): void
    {
        $item = $this->makeItem(
            '<enclosure url="https://example.com/enc.jpg" type="image/jpeg"/>'
            . '<media:content url="https://example.com/media.jpg"/>',
        );

        self::assertSame('https://example.com/enc.jpg', $this->invoke('extractImageUrl', $item, ''));
    }

    public function testExtractImageUrlSkipsNonImageEnclosure(): void
    {
        $item = $this->makeItem(
            '<enclosure url="https://example.com/audio.mp3" type="audio/mpeg"/>'
            . '<media:content url="https://example.com/media.jpg"/>',
        );

        self::assertSame('https://example.com/media.jpg', $this->invoke('extractImageUrl', $item, ''));
    }

    public function testExtractImageUrlFallsBackToHtmlImg(): void
    {
        $item = $this->makeItem('<title>No media here</title>');

        $html = '<p>Intro</p><img src="https://example.com/in-body.jpg" alt="x">';

        self::assertSame('https://example.com/in-body.jpg', $this->invoke('extractImageUrl', $item, $html));
    }

    public function testExtractImageUrlReturnsNullWhenNoImagePresent(): void
    {
        $item = $this->makeItem('<title>Nothing</title>');

        self::assertNull($this->invoke('extractImageUrl', $item, '<p>text only</p>'));
    }

    // ------------------------------------------------------------------
    // Feed parsing (RSS / Atom)
    // ------------------------------------------------------------------

    public function testParseRssItemsNormalizesAllFields(): void
    {
        $rss = '<?xml version="1.0"?>'
            . '<rss xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:media="http://search.yahoo.com/mrss/">'
            . '<channel><item>'
            . '<title>RSS &lt;b&gt;Title&lt;/b&gt;</title>'
            . '<description>&lt;p&gt;Hi&lt;/p&gt;&lt;script&gt;evil()&lt;/script&gt;</description>'
            . '<link>https://example.com/a</link>'
            . '<pubDate>Fri, 20 Jun 2026 17:46:00 +0200</pubDate>'
            . '<category>Politik</category>'
            . '<category>Wirtschaft</category>'
            . '<media:content url="https://example.com/img.jpg" medium="image"/>'
            . '</item></channel></rss>';

        $xml = $this->invoke('parseXmlSafely', $rss);
        $items = $this->invoke('parseRssItems', $xml, 'https://news.example.com/feed', []);

        self::assertCount(1, $items);
        $item = $items[0];

        self::assertSame('RSS Title', $item['title']);
        self::assertSame('<p>Hi</p>', $item['description']);
        self::assertSame('https://example.com/a', $item['link']);
        self::assertSame(['Politik', 'Wirtschaft'], $item['categories']);
        self::assertSame('https://example.com/img.jpg', $item['image']);
        self::assertSame('https://news.example.com/feed', $item['source']);
        self::assertSame('News', $item['sourceName']);
        // Date is normalized to ISO-8601 and represents the same instant as the input.
        self::assertNotNull($item['date']);
        self::assertSame(strtotime('Fri, 20 Jun 2026 17:46:00 +0200'), strtotime($item['date']));
    }

    public function testParseRssItemsUsesContentEncodedForImageAndDropsNonHttpLink(): void
    {
        $rss = '<?xml version="1.0"?>'
            . '<rss xmlns:content="http://purl.org/rss/1.0/modules/content/">'
            . '<channel><item>'
            . '<title>No media</title>'
            . '<description>Plain summary</description>'
            . '<link>javascript:alert(1)</link>'
            . '<content:encoded><![CDATA[<p>Body <img src="https://example.com/enc.jpg" alt="x"></p>]]></content:encoded>'
            . '</item></channel></rss>';

        $xml = $this->invoke('parseXmlSafely', $rss);
        $items = $this->invoke('parseRssItems', $xml, 'https://example.com/feed', []);

        self::assertSame('https://example.com/enc.jpg', $items[0]['image']);
        self::assertSame('', $items[0]['link']);
    }

    public function testParseRssItemsAppliesExplicitSourceName(): void
    {
        $rss = '<?xml version="1.0"?><rss><channel><item><title>x</title>'
            . '<link>https://example.com/a</link></item></channel></rss>';

        $xml = $this->invoke('parseXmlSafely', $rss);
        $items = $this->invoke('parseRssItems', $xml, 'https://example.com/feed', ['https://example.com/feed' => 'My Source']);

        self::assertSame('My Source', $items[0]['sourceName']);
    }

    public function testParseAtomItemsNormalizesAllFields(): void
    {
        $atom = '<?xml version="1.0"?>'
            . '<feed xmlns="http://www.w3.org/2005/Atom">'
            . '<entry>'
            . '<title>Atom &amp; Title</title>'
            . '<summary>Ignored summary</summary>'
            . '<content type="html">&lt;p&gt;Body&lt;/p&gt;&lt;script&gt;x()&lt;/script&gt;</content>'
            . '<link rel="alternate" href="https://example.com/post"/>'
            . '<published>2026-06-20T10:00:00Z</published>'
            . '<updated>2026-06-21T10:00:00Z</updated>'
            . '<category term="Tech"/>'
            . '</entry>'
            . '</feed>';

        $xml = $this->invoke('parseXmlSafely', $atom);
        $items = $this->invoke('parseAtomItems', $xml, 'https://example.org/atom', []);

        self::assertCount(1, $items);
        $item = $items[0];

        self::assertSame('Atom & Title', $item['title']);
        // content is preferred over summary, then sanitized.
        self::assertSame('<p>Body</p>', $item['description']);
        self::assertSame('https://example.com/post', $item['link']);
        self::assertSame(['Tech'], $item['categories']);
        // updated is preferred over published.
        self::assertSame(strtotime('2026-06-21T10:00:00Z'), strtotime($item['date']));
    }

    public function testParseAtomItemsFallsBackToSummaryWhenNoContent(): void
    {
        $atom = '<?xml version="1.0"?>'
            . '<feed xmlns="http://www.w3.org/2005/Atom">'
            . '<entry><title>t</title><summary>Just a summary</summary>'
            . '<link href="https://example.com/x"/></entry></feed>';

        $xml = $this->invoke('parseXmlSafely', $atom);
        $items = $this->invoke('parseAtomItems', $xml, 'https://example.org/atom', []);

        self::assertSame('Just a summary', $items[0]['description']);
    }

    public function testResolveAtomLinkPrefersAlternate(): void
    {
        $a = $this->atomChildren(
            '<link rel="self" href="https://example.com/self"/>'
            . '<link rel="alternate" href="https://example.com/alt"/>',
        );

        self::assertSame('https://example.com/alt', $this->invoke('resolveAtomLink', $a));
    }

    public function testResolveAtomLinkFallsBackToRellessLink(): void
    {
        $a = $this->atomChildren(
            '<link rel="enclosure" href="https://example.com/enc"/>'
            . '<link href="https://example.com/plain"/>',
        );

        self::assertSame('https://example.com/plain', $this->invoke('resolveAtomLink', $a));
    }

    public function testResolveAtomLinkFallsBackToFirstHref(): void
    {
        $a = $this->atomChildren('<link rel="self" href="https://example.com/only-self"/>');

        self::assertSame('https://example.com/only-self', $this->invoke('resolveAtomLink', $a));
    }

    /**
     * Return the Atom-namespaced children of a single <entry> built from the
     * given inner markup, matching what parseAtomItems passes to resolveAtomLink.
     */
    private function atomChildren(string $inner): \SimpleXMLElement
    {
        $xml = '<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom"><entry>'
            . $inner . '</entry></feed>';

        return $this->invoke('parseXmlSafely', $xml)->entry[0]->children('http://www.w3.org/2005/Atom');
    }

    // ------------------------------------------------------------------
    // Date handling
    // ------------------------------------------------------------------

    public function testParseDateReturnsNullForEmptyString(): void
    {
        self::assertNull($this->invoke('parseDate', ''));
    }

    public function testParseDateReturnsNullForUnparseableString(): void
    {
        self::assertNull($this->invoke('parseDate', 'not a date at all'));
    }

    public function testParseDateNormalizesValidRfc822Date(): void
    {
        $result = $this->invoke('parseDate', 'Fri, 20 Jun 2026 17:46:00 +0200');

        self::assertIsString($result);
        // Same absolute instant, regardless of the server's default timezone.
        self::assertSame(strtotime('Fri, 20 Jun 2026 17:46:00 +0200'), strtotime($result));
    }

    public function testGetDateGroupKeyBuckets(): void
    {
        // Build inputs as absolute offsets from now so the day difference is an
        // exact multiple of 86400 and the boundaries are not flaky across DST.
        $cases = [
            [0, 'Today'],
            [1, 'Yesterday'],
            [7, 'This Week'],
            [8, 'This Month'],
            [30, 'This Month'],
            [31, 'Last 3 Months'],
            [90, 'Last 3 Months'],
            [91, 'Older'],
        ];

        foreach ($cases as [$daysAgo, $expected]) {
            $input = date('c', time() - ($daysAgo * 86400));
            self::assertSame($expected, $this->invoke('getDateGroupKey', $input), "offset {$daysAgo}d");
        }
    }

    public function testGetDateGroupKeyReturnsNoDateForEmptyAndGarbage(): void
    {
        self::assertSame('No Date', $this->invoke('getDateGroupKey', ''));
        self::assertSame('No Date', $this->invoke('getDateGroupKey', 'rubbish'));
    }

    // ------------------------------------------------------------------
    // Category detection, grouping & slicing
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function detectCategoryFromUrlProvider(): array
    {
        return [
            'german slug maps to itself' => ['https://x.de/politik/article', 'Politik'],
            'english economy maps to german' => ['https://x.com/economy/story', 'Wirtschaft'],
            'english sports maps to german' => ['https://x.com/sports/match', 'Sport'],
            'english technology maps to digital' => ['https://x.com/technology/ai', 'Digital'],
            'trailing slug without slash matches' => ['https://x.de/kultur', 'Kultur'],
            'unknown segment is not detected' => ['https://x.com/lifestyle/story', null],
            'no path is not detected' => ['https://x.com', null],
        ];
    }

    #[DataProvider('detectCategoryFromUrlProvider')]
    public function testDetectCategoryFromUrl(string $url, ?string $expected): void
    {
        self::assertSame($expected, $this->invoke('detectCategoryFromUrl', $url));
    }

    public function testGroupItemsDateModeBucketsAndSortsByDate(): void
    {
        $today = date('c');
        $longAgo = date('c', time() - (200 * 86400));

        $items = [
            ['link' => 'https://example.com/old', 'date' => $longAgo],
            ['link' => 'https://example.com/new', 'date' => $today],
        ];

        $result = $this->invoke('groupItems', $items, 'date', [], []);

        self::assertArrayHasKey('Today', $result);
        self::assertArrayHasKey('Older', $result);
        self::assertSame('https://example.com/new', $result['Today'][0]['link']);
        self::assertSame('https://example.com/old', $result['Older'][0]['link']);
    }

    public function testGroupItemsSourceModeRestoresDisplayName(): void
    {
        $items = [
            ['link' => 'https://example.com/1', 'sourceName' => 'BBC News'],
            ['link' => 'https://example.com/2', 'sourceName' => 'BBC News'],
            ['link' => 'https://example.com/3', 'sourceName' => 'taz'],
        ];

        $result = $this->invoke('groupItems', $items, 'source', [], []);

        // Keys are the original (non-lowercased) display names, grouped by source.
        self::assertArrayHasKey('BBC News', $result);
        self::assertArrayHasKey('taz', $result);
        self::assertCount(2, $result['BBC News']);
        self::assertCount(1, $result['taz']);
    }

    public function testSortAndSliceGroupsSortsByDateDescAndAppliesMaxItems(): void
    {
        $grouped = [
            'Sport' => [
                ['link' => 'https://example.com/s1', 'date' => '2026-01-01T00:00:00+00:00'],
                ['link' => 'https://example.com/s3', 'date' => '2026-03-01T00:00:00+00:00'],
                ['link' => 'https://example.com/s2', 'date' => '2026-02-01T00:00:00+00:00'],
            ],
        ];

        $result = $this->invoke('sortAndSliceGroups', $grouped, 2);

        self::assertCount(2, $result['Sport']);
        // Newest first, and the oldest (s1) dropped by the maxItems=2 slice.
        self::assertSame('https://example.com/s3', $result['Sport'][0]['link']);
        self::assertSame('https://example.com/s2', $result['Sport'][1]['link']);
    }

    public function testSortAndSliceGroupsOrdersGroupKeysCaseInsensitively(): void
    {
        $grouped = [
            'Wirtschaft' => [['link' => 'https://example.com/w', 'date' => '2026-01-01T00:00:00+00:00']],
            'kultur' => [['link' => 'https://example.com/k', 'date' => '2026-01-01T00:00:00+00:00']],
            'Politik' => [['link' => 'https://example.com/p', 'date' => '2026-01-01T00:00:00+00:00']],
        ];

        $result = $this->invoke('sortAndSliceGroups', $grouped, 0);

        self::assertSame(['kultur', 'Politik', 'Wirtschaft'], array_keys($result));
    }

    // ------------------------------------------------------------------
    // Bounded body reading
    // ------------------------------------------------------------------

    public function testReadBoundedBodyReturnsFullBodyWithinLimit(): void
    {
        $stream = Utils::streamFor('hello world');

        self::assertSame('hello world', $this->invoke('readBoundedBody', $stream));
    }

    public function testReadBoundedBodyReturnsNullWhenLimitExceeded(): void
    {
        // MAX_FEED_BYTES is 5 MiB; one byte over the limit must abort the read.
        $stream = Utils::streamFor(str_repeat('a', 5_242_881));

        self::assertNull($this->invoke('readBoundedBody', $stream));
    }

    // ------------------------------------------------------------------
    // SSRF guard: resolved IPs for literals
    // ------------------------------------------------------------------

    public function testResolveValidatedIpsReturnsPublicIpLiteral(): void
    {
        self::assertSame(['8.8.8.8'], $this->invoke('resolveValidatedIps', 'http://8.8.8.8/feed.xml'));
    }

    public function testResolveValidatedIpsRejectsPrivateIpLiteral(): void
    {
        self::assertNull($this->invoke('resolveValidatedIps', 'http://10.0.0.1/feed.xml'));
    }

    // ------------------------------------------------------------------
    // Orchestration via a cached hit (no network)
    // ------------------------------------------------------------------

    public function testFetchGroupedByCategoryDeduplicatesAndGroupsAcrossFeeds(): void
    {
        $feedA = 'https://a.example/feed';
        $feedB = 'https://b.example/feed';

        $this->injectCacheReturning([
            self::cacheId($feedA) => [
                ['link' => 'https://x/1', 'title' => 'One', 'categories' => ['Politik'], 'date' => '2026-03-01T00:00:00+00:00', 'source' => $feedA, 'sourceName' => 'A'],
                ['link' => 'https://x/dup', 'title' => 'Dup A', 'categories' => ['Politik'], 'date' => '2026-02-01T00:00:00+00:00', 'source' => $feedA, 'sourceName' => 'A'],
            ],
            self::cacheId($feedB) => [
                ['link' => 'https://x/dup', 'title' => 'Dup B', 'categories' => ['Sport'], 'date' => '2026-04-01T00:00:00+00:00', 'source' => $feedB, 'sourceName' => 'B'],
                ['link' => 'https://x/2', 'title' => 'Two', 'categories' => ['Sport'], 'date' => '2026-01-01T00:00:00+00:00', 'source' => $feedB, 'sourceName' => 'B'],
            ],
        ]);

        $result = $this->invoke('fetchGroupedByCategory', [$feedA, $feedB], 0, 3600, [], [], [], 'category');

        self::assertArrayHasKey('Politik', $result);
        self::assertArrayHasKey('Sport', $result);
        // The duplicate link is kept from its first occurrence (feed A / Politik),
        // so it must not also appear under Sport.
        self::assertContains('https://x/dup', array_column($result['Politik'], 'link'));
        self::assertNotContains('https://x/dup', array_column($result['Sport'], 'link'));
        self::assertSame(['https://x/2'], array_column($result['Sport'], 'link'));
    }

    public function testFetchGroupedByCategoryAppliesMaxItemsPerGroup(): void
    {
        $feed = 'https://a.example/feed';
        $this->injectCacheReturning([
            self::cacheId($feed) => [
                ['link' => 'https://x/1', 'categories' => ['Politik'], 'date' => '2026-01-01T00:00:00+00:00', 'source' => $feed, 'sourceName' => 'A'],
                ['link' => 'https://x/3', 'categories' => ['Politik'], 'date' => '2026-03-01T00:00:00+00:00', 'source' => $feed, 'sourceName' => 'A'],
                ['link' => 'https://x/2', 'categories' => ['Politik'], 'date' => '2026-02-01T00:00:00+00:00', 'source' => $feed, 'sourceName' => 'A'],
            ],
        ]);

        $result = $this->invoke('fetchGroupedByCategory', [$feed], 2, 3600, [], [], [], 'category');

        // Newest two only, sorted descending.
        self::assertSame(['https://x/3', 'https://x/2'], array_column($result['Politik'], 'link'));
    }

    public function testWarmCacheReturnsTrueOnCacheHit(): void
    {
        $url = 'https://example.com/feed';
        $this->injectCacheReturning([self::cacheId($url) => []]);

        self::assertTrue($this->invoke('warmCache', $url, 3600, []));
    }
}
