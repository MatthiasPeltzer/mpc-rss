<?php

declare(strict_types=1);

namespace Mpc\MpcRss\Service;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\HtmlSanitizer\Behavior;
use TYPO3\HtmlSanitizer\Behavior\Attr;
use TYPO3\HtmlSanitizer\Behavior\Attr\UriAttrValueBuilder;
use TYPO3\HtmlSanitizer\Behavior\Tag;
use TYPO3\HtmlSanitizer\Sanitizer;
use TYPO3\HtmlSanitizer\Visitor\CommonVisitor;

final class FeedService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const CATEGORY_PATTERNS = [
        'politik' => 'Politik',
        'wirtschaft' => 'Wirtschaft',
        'kultur' => 'Kultur',
        'sport' => 'Sport',
        'wissen' => 'Wissen',
        'digital' => 'Digital',
        'gesellschaft' => 'Gesellschaft',
        'economy' => 'Wirtschaft',
        'politics' => 'Politik',
        'culture' => 'Kultur',
        'sports' => 'Sport',
        'science' => 'Wissen',
        'technology' => 'Digital',
    ];

    private const DEFAULT_GROUP = 'General';
    private const UNKNOWN_SOURCE = 'Unknown Source';
    private const DEFAULT_SOURCE = 'RSS';

    /** Maximum number of bytes read from a remote feed before aborting. */
    private const MAX_FEED_BYTES = 5_242_880;

    /** Maximum number of redirects followed when fetching a feed. */
    private const MAX_REDIRECTS = 3;

    /** Maximum URL length to prevent extremely long URLs from causing issues. */
    private const MAX_URL_LENGTH = 2048;
    private const DATE_LABELS = [
        'noDate' => 'No Date',
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'thisWeek' => 'This Week',
        'thisMonth' => 'This Month',
        'last3Months' => 'Last 3 Months',
        'older' => 'Older',
    ];

    private ?FrontendInterface $cache = null;
    private ?Sanitizer $htmlSanitizer = null;

    public function __construct(
        private readonly CacheManager $cacheManager,
        private readonly RequestFactory $requestFactory,
    ) {}

    private function getCache(): FrontendInterface
    {
        return $this->cache ??= $this->cacheManager->getCache('mpc_rss');
    }

    /**
     * Warm the cache for a single feed URL without grouping or sorting.
     *
     * @param array<string, string> $sourceNames
     * @return bool True when the feed was fetched and parsed (or served from cache), false on failure.
     */
    public function warmCache(string $url, int $cacheLifetime, array $sourceNames = []): bool
    {
        if (strlen($url) > self::MAX_URL_LENGTH) {
            $this->logger?->warning('Feed URL exceeds maximum allowed length', ['url' => substr($url, 0, 100) . '...']);
            return false;
        }
        return $this->fetchFeedItems($url, $cacheLifetime, $sourceNames) !== null;
    }

    /**
     * @param list<string> $urls
     * @param array<string, string> $sourceNames URL => source name mapping
     * @return array<string, list<array<string,mixed>>> group => items
     */
    public function fetchGroupedByCategory(
        array $urls,
        int $maxItems,
        int $cacheLifetime,
        array $includeCategories = [],
        array $excludeCategories = [],
        array $sourceNames = [],
        string $groupingMode = 'category',
    ): array {
        $items = [];
        foreach ($urls as $url) {
            $fetched = $this->fetchFeedItems($url, $cacheLifetime, $sourceNames);
            if ($fetched !== null && $fetched !== []) {
                array_push($items, ...$fetched);
            }
        }

        $items = $this->deduplicateItems($items);
        $grouped = $this->groupItems($items, $groupingMode, $includeCategories, $excludeCategories);

        return $this->sortAndSliceGroups($grouped, $maxItems);
    }

    /**
     * Fetch and parse a single feed URL, returning cached items when available.
     *
     * @param array<string, string> $sourceNames
     * @return list<array<string, mixed>>|null Items on success (possibly empty), null on fetch/parse failure.
     */
    private function fetchFeedItems(string $url, int $cacheLifetime, array $sourceNames): ?array
    {
        $cacheIdentifier = 'feed_' . md5($url);
        $feedData = $this->getCache()->get($cacheIdentifier);
        if ($feedData !== false) {
            return $feedData;
        }

        $body = $this->fetchFeedBody($url);
        if ($body === null) {
            $this->cacheNegativeResult($cacheIdentifier, $cacheLifetime);
            return null;
        }
        if ($body === '') {
            $this->logger?->warning('Fetching RSS feed returned empty body', ['url' => $url]);
            $this->cacheNegativeResult($cacheIdentifier, $cacheLifetime);
            return null;
        }

        $xml = $this->parseXmlSafely($body);

        if ($xml === null) {
            $this->logger?->warning('Failed to parse RSS feed XML', ['url' => $url]);
            $this->cacheNegativeResult($cacheIdentifier, $cacheLifetime);
            return null;
        }

        $feedItems = $this->getXmlChild($xml->channel ?? null, 'item') !== null
            ? $this->parseRssItems($xml, $url, $sourceNames)
            : $this->parseAtomItems($xml, $url, $sourceNames);

        // Cache successful parses, including legitimately empty feeds, so a feed that
        // currently has no items is not re-fetched on every request.
        $this->getCache()->set($cacheIdentifier, $feedItems, ['mpc_rss', 'mpc_rss_feed'], $cacheLifetime);

        return $feedItems;
    }

    /**
     * Fetch a feed body with SSRF-safe, IP-pinned requests.
     *
     * For every hop (the initial request and each redirect) the target URL is
     * re-validated and the connection is pinned to the exact public IP(s) it
     * resolves to (via CURLOPT_RESOLVE). This closes the DNS-rebinding / TOCTOU
     * window: the host cannot resolve to a public address during validation and
     * then to a private/loopback address when the socket is actually opened.
     * Redirects are followed manually (Guzzle auto-redirects disabled) so every
     * hop goes through the same validate-and-pin path.
     *
     * @return string|null The response body (possibly empty) on HTTP 200, or null on any failure.
     */
    private function fetchFeedBody(string $url): ?string
    {
        $currentUrl = $url;
        $redirectsLeft = self::MAX_REDIRECTS;

        while (true) {
            $ips = $this->resolveValidatedIps($currentUrl);
            if ($ips === null) {
                $this->logger?->warning('Refusing to fetch RSS feed from a disallowed or non-public URL', ['url' => $currentUrl]);
                return null;
            }

            try {
                $response = $this->requestFactory->request($currentUrl, 'GET', [
                    'headers' => [
                        'User-Agent' => 'MPC RSS TYPO3',
                        'Accept' => 'application/rss+xml, application/atom+xml;q=0.95, application/xml;q=0.9, */*;q=0.8',
                    ],
                    'timeout' => 10,
                    'connect_timeout' => 6,
                    // Redirects are followed by hand below so each hop is re-validated and re-pinned.
                    'allow_redirects' => false,
                    'stream' => true,
                    // Pin the connection to the public IP(s) just validated. The Host
                    // header, TLS SNI and certificate verification still use the original
                    // hostname, so only the resolution step is overridden. Honoured by the
                    // cURL handler; on the (rare) stream fallback this is a no-op and the
                    // per-hop validation above remains the active guard.
                    'curl' => $this->buildCurlResolveOption($currentUrl, $ips),
                ]);
            } catch (\Throwable $exception) {
                $this->logger?->warning('Fetching RSS feed failed', ['url' => $currentUrl, 'exception' => $exception]);
                return null;
            }

            $statusCode = $response->getStatusCode();

            if (in_array($statusCode, [301, 302, 303, 307, 308], true)) {
                if ($redirectsLeft <= 0) {
                    $this->logger?->warning('RSS feed exceeded the maximum number of redirects', ['url' => $url, 'maxRedirects' => self::MAX_REDIRECTS]);
                    return null;
                }
                $location = $response->getHeaderLine('Location');
                $nextUrl = $location !== '' ? $this->resolveRedirectLocation($currentUrl, $location) : null;
                if ($nextUrl === null) {
                    $this->logger?->warning('RSS feed redirect to a missing or unsupported location', ['url' => $currentUrl, 'location' => $location]);
                    return null;
                }
                $currentUrl = $nextUrl;
                $redirectsLeft--;
                continue;
            }

            if ($statusCode !== 200) {
                $this->logger?->warning('Fetching RSS feed returned unexpected status code', [
                    'url' => $currentUrl,
                    'statusCode' => $statusCode,
                ]);
                return null;
            }

            $body = $this->readBoundedBody($response->getBody());
            if ($body === null) {
                $this->logger?->warning('RSS feed exceeded the maximum allowed size', ['url' => $currentUrl, 'maxBytes' => self::MAX_FEED_BYTES]);
                return null;
            }
            return $body;
        }
    }

    /**
     * Build the CURLOPT_RESOLVE option that pins the URL's host (on its effective
     * port) to the supplied, already-validated IP addresses.
     *
     * @param list<string> $ips
     * @return array<int, list<string>>
     */
    private function buildCurlResolveOption(string $url, array $ips): array
    {
        // CURLOPT_RESOLVE only exists when ext-curl is loaded (and the Guzzle cURL
        // handler is in use). Without it, pinning is not possible and we fall back
        // to the per-hop validation in fetchFeedBody().
        if (!defined('CURLOPT_RESOLVE')) {
            return [];
        }

        $parts = parse_url($url);
        $host = isset($parts['host']) ? trim((string)$parts['host'], '[]') : '';

        // IP-literal hosts are connected to directly; there is no name resolution
        // to subvert, so pinning is unnecessary (and would be malformed for IPv6).
        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [];
        }

        $scheme = strtolower((string)($parts['scheme'] ?? 'http'));
        $port = (int)($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        return [CURLOPT_RESOLVE => [sprintf('%s:%d:%s', $host, $port, implode(',', $ips))]];
    }

    /**
     * Resolve a (possibly relative) redirect Location against the current URL and
     * return it only if it is an absolute http(s) URL with a host, null otherwise.
     */
    private function resolveRedirectLocation(string $currentUrl, string $location): ?string
    {
        try {
            $target = UriResolver::resolve(new Uri($currentUrl), new Uri($location));
        } catch (\Throwable) {
            return null;
        }

        $scheme = strtolower($target->getScheme());
        if (!in_array($scheme, ['http', 'https'], true) || $target->getHost() === '') {
            return null;
        }

        return (string)$target;
    }

    /**
     * Read a response body up to MAX_FEED_BYTES, returning null when the limit is exceeded.
     */
    private function readBoundedBody(\Psr\Http\Message\StreamInterface $stream): ?string
    {
        $body = '';
        while (!$stream->eof()) {
            $chunk = $stream->read(8192);
            if ($chunk === '') {
                break;
            }
            $body .= $chunk;
            if (strlen($body) > self::MAX_FEED_BYTES) {
                return null;
            }
        }
        return $body;
    }

    /**
     * Guard against SSRF: only allow http(s) URLs that resolve to public IP addresses.
     */
    private function isAllowedFeedUrl(string $url): bool
    {
        return $this->resolveValidatedIps($url) !== null;
    }

    /**
     * Validate a feed URL and return the public IP address(es) it resolves to, or
     * null when the URL is disallowed (non-http(s) scheme, localhost, private /
     * reserved range, or unresolvable). The returned IPs are used to pin the
     * outgoing connection so the host cannot rebind to a private address between
     * this check and the actual fetch.
     *
     * @return list<string>|null
     */
    private function resolveValidatedIps(string $url): ?array
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        $host = trim($parts['host'], '[]');
        $lowerHost = strtolower($host);

        // Reject localhost by name up-front. The IP-based checks below are authoritative,
        // but rejecting these obvious names early avoids a needless DNS lookup.
        if ($lowerHost === 'localhost' || str_ends_with($lowerHost, '.localhost')) {
            return null;
        }

        // Reject obvious loopback / private / link-local / reserved IPv4 literals before
        // resolving DNS. Anchored at the start so it matches real address prefixes
        // (the previous pattern was anchored with `$` and therefore never matched).
        if (preg_match('/^(0\.|10\.|127\.|169\.254\.|192\.0\.2\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/', $host)) {
            return null;
        }

        $ips = $this->resolveHostIps($host);
        if ($ips === []) {
            return null;
        }

        foreach ($ips as $ip) {
            if (!$this->isPublicIp($ip)) {
                return null;
            }
        }
        return $ips;
    }

    /**
     * @return list<string>
     */
    private function resolveHostIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $ips = [];
        // @ suppression: dns_get_record() emits a warning for hosts with no
        // A/AAAA records (or transient resolver failures); we handle that as an
        // empty result below rather than letting the warning surface.
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ip'])) {
                    $ips[] = (string)$record['ip'];
                } elseif (isset($record['ipv6'])) {
                    $ips[] = (string)$record['ipv6'];
                }
            }
        }

        if ($ips === []) {
            // @ suppression: gethostbyname() returns the unmodified host on
            // failure (checked below) but can still warn on malformed input.
            $resolved = @gethostbyname($host);
            if ($resolved !== $host && filter_var($resolved, FILTER_VALIDATE_IP) !== false) {
                $ips[] = $resolved;
            }
        }

        return $ips;
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    /**
     * @param array<string, string> $sourceNames
     * @return list<array<string, mixed>>
     */
    private function parseRssItems(\SimpleXMLElement $xml, string $url, array $sourceNames): array
    {
        $items = [];
        $rssItems = $this->getXmlChild($xml->channel ?? null, 'item');
        if ($rssItems === null) {
            return [];
        }

        foreach ($rssItems as $entry) {
            $title = $this->getXmlValue($entry, 'title');
            $description = $this->getXmlValue($entry, 'description');
            $link = $this->getXmlValue($entry, 'link');
            $pubDate = $this->getXmlValue($entry, 'pubDate');

            $categories = [];
            $categoryElements = $this->getXmlChild($entry, 'category');
            if ($categoryElements !== null) {
                foreach ($categoryElements as $cat) {
                    $categories[] = (string)$cat;
                }
            }

            $contentNs = $entry->children('http://purl.org/rss/1.0/modules/content/');
            $encoded = $this->getXmlValue($contentNs, 'encoded');
            $htmlSource = $encoded !== '' ? $encoded : $description;

            $items[] = [
                'title' => strip_tags($title),
                'description' => $this->sanitizeHtml($description),
                'link' => $this->sanitizeUrl($link),
                'date' => $this->parseDate($pubDate),
                'categories' => $categories,
                'image' => $this->sanitizeUrl($this->extractImageUrl($entry, $htmlSource)),
                'authors' => [],
                'source' => $url,
                'sourceName' => $this->detectSourceName($url, $sourceNames),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, string> $sourceNames
     * @return list<array<string, mixed>>
     */
    private function parseAtomItems(\SimpleXMLElement $xml, string $url, array $sourceNames): array
    {
        $items = [];
        $xml->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
        $entries = $xml->xpath('//atom:entry');

        if (!is_array($entries)) {
            return [];
        }

        foreach ($entries as $entry) {
            $a = $entry->children('http://www.w3.org/2005/Atom');
            $title = $this->getXmlValue($a, 'title');
            $contentValue = $this->getXmlValue($a, 'content');
            $summary = $this->getXmlValue($a, 'summary');
            $description = $contentValue !== '' ? $contentValue : $summary;
            $link = $this->resolveAtomLink($a);

            $updated = $this->getXmlValue($a, 'updated');
            $published = $this->getXmlValue($a, 'published');
            $dateStr = $updated !== '' ? $updated : $published;

            $categories = [];
            $categoryElements = $this->getXmlChild($a, 'category');
            if ($categoryElements !== null) {
                foreach ($categoryElements as $cat) {
                    $term = isset($cat['term']) ? (string)$cat['term'] : '';
                    $categories[] = $term !== '' ? $term : (string)$cat;
                }
            }

            $items[] = [
                'title' => strip_tags($title),
                'description' => $this->sanitizeHtml($description),
                'link' => $this->sanitizeUrl($link),
                'date' => $this->parseDate($dateStr),
                'categories' => $categories,
                'image' => $this->sanitizeUrl($this->extractImageUrl($entry, $description)),
                'authors' => [],
                'source' => $url,
                'sourceName' => $this->detectSourceName($url, $sourceNames),
            ];
        }

        return $items;
    }

    private function resolveAtomLink(\SimpleXMLElement $atomElement): string
    {
        $linkElements = $this->getXmlChild($atomElement, 'link');
        if ($linkElements === null) {
            return '';
        }

        $fallback = '';
        foreach ($linkElements as $l) {
            $rel = isset($l['rel']) ? (string)$l['rel'] : '';
            $href = isset($l['href']) ? (string)$l['href'] : '';
            if ($href !== '' && ($rel === '' || $rel === 'alternate')) {
                if ($rel === 'alternate') {
                    return $href;
                }
                $fallback = $href;
            }
        }

        if ($fallback !== '') {
            return $fallback;
        }

        return isset($linkElements[0]['href']) ? (string)$linkElements[0]['href'] : '';
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function deduplicateItems(array $items): array
    {
        $deduped = [];
        foreach ($items as $item) {
            $link = $item['link'] ?? '';
            if ($link !== '' && !isset($deduped[$link])) {
                $deduped[$link] = $item;
            } elseif ($link === '') {
                $deduped[] = $item;
            }
        }
        return array_values($deduped);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<string> $includeCategories
     * @param list<string> $excludeCategories
     * @return array<string, list<array<string, mixed>>>
     */
    private function groupItems(
        array $items,
        string $groupingMode,
        array $includeCategories,
        array $excludeCategories,
    ): array {
        if ($groupingMode === 'none' || $groupingMode === 'date') {
            usort($items, self::dateDescComparator(...));

            if ($groupingMode === 'date') {
                $grouped = [];
                foreach ($items as $item) {
                    $grouped[$this->getDateGroupKey($item['date'] ?? '')][] = $item;
                }
                return $grouped;
            }

            return [self::DEFAULT_GROUP => $items];
        }

        $normalizedInclude = $includeCategories !== [] ? array_map('mb_strtolower', $includeCategories) : [];
        $normalizedExclude = $excludeCategories !== [] ? array_map('mb_strtolower', $excludeCategories) : [];

        $grouped = [];
        $sourceNameMapping = [];

        foreach ($items as $item) {
            if ($groupingMode === 'category') {
                $groupKey = $this->resolveCategoryGroupKey($item, $normalizedInclude, $normalizedExclude);
                if ($groupKey === null) {
                    continue;
                }
            } else {
                $sourceName = $item['sourceName'] ?? self::UNKNOWN_SOURCE;
                $normalizedKey = mb_strtolower($sourceName);
                $sourceNameMapping[$normalizedKey] ??= $sourceName;
                $groupKey = $normalizedKey;
            }

            $grouped[$groupKey][] = $item;
        }

        if ($groupingMode === 'source' && $sourceNameMapping !== []) {
            $renamed = [];
            foreach ($grouped as $key => $groupItems) {
                $renamed[$sourceNameMapping[$key] ?? $key] = $groupItems;
            }
            $grouped = $renamed;
        }

        return $grouped;
    }

    /**
     * Determine the category group key for an item, or null if the item is filtered out.
     *
     * @param array<string, mixed> $item
     * @param list<string> $normalizedInclude Pre-lowercased include filter
     * @param list<string> $normalizedExclude Pre-lowercased exclude filter
     */
    private function resolveCategoryGroupKey(array $item, array $normalizedInclude, array $normalizedExclude): ?string
    {
        $categories = $item['categories'] ?? [];

        if ($categories === []) {
            $sourceUrl = $item['source'] ?? '';
            $sourceName = $item['sourceName'] ?? '';
            $detectedCategory = $this->detectCategoryFromUrl($sourceUrl);
            $categories = $detectedCategory !== null
                ? [$detectedCategory]
                : ($sourceName !== '' ? [$sourceName] : [self::DEFAULT_GROUP]);
        }

        $normalizedItemCategories = array_map('mb_strtolower', $categories);

        if ($normalizedInclude !== [] && !array_intersect($normalizedItemCategories, $normalizedInclude)) {
            return null;
        }

        if ($normalizedExclude !== [] && array_intersect($normalizedItemCategories, $normalizedExclude)) {
            return null;
        }

        return $categories[0] ?? self::DEFAULT_GROUP;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $grouped
     * @return array<string, list<array<string, mixed>>>
     */
    private function sortAndSliceGroups(array $grouped, int $maxItems): array
    {
        foreach ($grouped as &$groupItems) {
            usort($groupItems, self::dateDescComparator(...));
            if ($maxItems > 0) {
                $groupItems = array_slice($groupItems, 0, $maxItems);
            }
        }
        unset($groupItems);

        ksort($grouped, SORT_STRING | SORT_FLAG_CASE);
        return $grouped;
    }

    private static function dateDescComparator(array $a, array $b): int
    {
        return strcmp((string)($b['date'] ?? ''), (string)($a['date'] ?? ''));
    }

    /**
     * Cache a fetch/parse failure as an empty item list for a capped lifetime so a
     * broken or unreachable feed is not re-fetched on every request.
     *
     * Note: the stored value (`[]`) is intentionally indistinguishable from a
     * legitimately empty feed on the next cache hit — both degrade to "no items".
     * The shorter lifetime (and the `mpc_rss_feed_error` tag) is what separates a
     * failure from a successful empty parse.
     */
    private function cacheNegativeResult(string $cacheIdentifier, int $cacheLifetime): void
    {
        $this->getCache()->set($cacheIdentifier, [], ['mpc_rss', 'mpc_rss_feed_error'], min($cacheLifetime, 300));
    }

    /**
     * Sanitize external feed HTML through the TYPO3 HtmlSanitizer, allowing only a
     * minimal set of inline tags. Unknown tags (and their content) are removed,
     * disallowed attributes are stripped, and `<a href>` is restricted to http(s).
     */
    private function sanitizeHtml(string $html): string
    {
        if ($html === '') {
            return '';
        }
        return trim($this->getHtmlSanitizer()->sanitize($html));
    }

    private function getHtmlSanitizer(): Sanitizer
    {
        if ($this->htmlSanitizer !== null) {
            return $this->htmlSanitizer;
        }

        $href = (new Attr('href'))->addValues(
            ...(new UriAttrValueBuilder())->allowSchemes('http', 'https')->getValues()
        );

        $behavior = (new Behavior())
            ->withName('mpc_rss')
            ->withTags(
                new Tag('p', Tag::ALLOW_CHILDREN),
                new Tag('strong', Tag::ALLOW_CHILDREN),
                new Tag('em', Tag::ALLOW_CHILDREN),
                new Tag('b', Tag::ALLOW_CHILDREN),
                new Tag('i', Tag::ALLOW_CHILDREN),
                new Tag('br'),
                (new Tag('a', Tag::ALLOW_CHILDREN))->addAttrs($href),
            );

        return $this->htmlSanitizer = new Sanitizer($behavior, new CommonVisitor($behavior));
    }

    /**
     * Return the URL only if it uses an HTTP(S) scheme, empty string otherwise.
     */
    private function sanitizeUrl(?string $url): string
    {
        if ($url === null || $url === '') {
            return '';
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!is_string($scheme) || !in_array(strtolower($scheme), ['http', 'https'], true)) {
            return '';
        }
        return $url;
    }

    // ------------------------------------------------------------------
    // XML helpers
    // ------------------------------------------------------------------

    /**
     * Parse XML string safely with XXE protection.
     *
     * On PHP 8 / libxml >= 2.9 external entity loading is disabled by default, so the
     * safest configuration is to NOT request entity substitution or DTD loading:
     *  - LIBXML_NOENT would *expand* entities (enabling "billion laughs" / XXE expansion),
     *  - LIBXML_DTDLOAD would load the external DTD subset.
     * Both were previously set under the (incorrect) assumption that they hardened parsing.
     * We pass LIBXML_NONET (refuse any network access) and LIBXML_NOCDATA (fold CDATA into
     * text) only.
     */
    private function parseXmlSafely(string $xmlString): ?\SimpleXMLElement
    {
        // Reject any document declaring a DTD. Legitimate RSS/Atom feeds never use a
        // DOCTYPE, so refusing them eliminates XXE and entity-expansion ("billion
        // laughs") vectors outright, regardless of libxml flag behaviour.
        if (preg_match('/<!DOCTYPE/i', $xmlString) === 1) {
            return null;
        }

        $previousUseErrors = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string(
                $xmlString,
                'SimpleXMLElement',
                LIBXML_NOCDATA | LIBXML_NONET
            );
            return $xml === false ? null : $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseErrors);
        }
    }

    private function getXmlChild(?\SimpleXMLElement $element, string $property): ?\SimpleXMLElement
    {
        if ($element === null) {
            return null;
        }
        try {
            $child = $element->{$property};
            return $child !== null && count($child) > 0 ? $child : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function getXmlValue(?\SimpleXMLElement $element, string $property): string
    {
        if ($element === null) {
            return '';
        }
        try {
            $value = $element->{$property};
            return $value !== null ? (string)$value : '';
        } catch (\Throwable) {
            return '';
        }
    }

    private function parseDate(string $dateStr): ?string
    {
        if ($dateStr === '') {
            return null;
        }
        // @ suppression: strtotime() can emit a warning for unparseable,
        // feed-supplied date strings; we treat those as "no date" (null).
        $timestamp = @strtotime($dateStr);
        return $timestamp !== false ? date('c', $timestamp) : null;
    }

    private function extractImageUrl(\SimpleXMLElement $entry, string $htmlContent = ''): ?string
    {
        $enclosures = $this->getXmlChild($entry, 'enclosure');
        if ($enclosures !== null) {
            foreach ($enclosures as $enclosure) {
                $type = isset($enclosure['type']) ? (string)$enclosure['type'] : '';
                $urlAttr = isset($enclosure['url']) ? (string)$enclosure['url'] : '';
                if ($urlAttr !== '' && ($type === '' || str_starts_with($type, 'image'))) {
                    return $urlAttr;
                }
            }
        }

        $mediaNs = $entry->children('http://search.yahoo.com/mrss/');

        $mediaContent = $this->getXmlChild($mediaNs, 'content');
        if ($mediaContent !== null) {
            foreach ($mediaContent as $mc) {
                $urlAttr = isset($mc['url']) ? (string)$mc['url'] : '';
                if ($urlAttr !== '') {
                    return $urlAttr;
                }
            }
        }

        $mediaThumbnail = $this->getXmlChild($mediaNs, 'thumbnail');
        if ($mediaThumbnail !== null) {
            $thumbUrl = isset($mediaThumbnail['url']) ? (string)$mediaThumbnail['url'] : '';
            if ($thumbUrl !== '') {
                return $thumbUrl;
            }
        }

        if ($htmlContent !== '' && preg_match('/<img[^>]+src="([^"]+)"/i', $htmlContent, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * @param array<string, string> $sourceNames
     */
    private function detectSourceName(string $url, array $sourceNames): string
    {
        if (isset($sourceNames[$url]) && $sourceNames[$url] !== '') {
            return $sourceNames[$url];
        }

        $parsedUrl = parse_url($url);
        if (isset($parsedUrl['host'])) {
            $host = preg_replace('/^www\./', '', $parsedUrl['host']) ?? $parsedUrl['host'];
            $parts = explode('.', $host);
            if ($parts[0] !== '') {
                return ucfirst($parts[0]);
            }
        }

        return self::DEFAULT_SOURCE;
    }

    private function getDateGroupKey(string $dateStr): string
    {
        if ($dateStr === '') {
            return self::DATE_LABELS['noDate'];
        }

        $timestamp = @strtotime($dateStr);
        if ($timestamp === false) {
            return self::DATE_LABELS['noDate'];
        }

        $daysDiff = (int)floor((time() - $timestamp) / 86400);

        return match (true) {
            $daysDiff === 0 => self::DATE_LABELS['today'],
            $daysDiff === 1 => self::DATE_LABELS['yesterday'],
            $daysDiff <= 7 => self::DATE_LABELS['thisWeek'],
            $daysDiff <= 30 => self::DATE_LABELS['thisMonth'],
            $daysDiff <= 90 => self::DATE_LABELS['last3Months'],
            default => self::DATE_LABELS['older'],
        };
    }

    private function detectCategoryFromUrl(string $url): ?string
    {
        $pattern = '#/(' . implode('|', array_keys(self::CATEGORY_PATTERNS)) . ')(?:/|$)#i';
        if (preg_match($pattern, $url, $matches)) {
            return self::CATEGORY_PATTERNS[strtolower($matches[1])] ?? ucfirst(strtolower($matches[1]));
        }
        return null;
    }
}
