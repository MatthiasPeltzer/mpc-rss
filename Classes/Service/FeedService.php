<?php

declare(strict_types=1);

namespace Mpc\MpcRss\Service;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Http\RequestFactory;

class FeedService implements LoggerAwareInterface
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
     */
    public function warmCache(string $url, int $cacheLifetime, array $sourceNames = []): void
    {
        $this->fetchFeedItems($url, $cacheLifetime, $sourceNames);
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
            array_push($items, ...$this->fetchFeedItems($url, $cacheLifetime, $sourceNames));
        }

        $items = $this->deduplicateItems($items);
        $grouped = $this->groupItems($items, $groupingMode, $includeCategories, $excludeCategories);

        return $this->sortAndSliceGroups($grouped, $maxItems);
    }

    /**
     * Fetch and parse a single feed URL, returning cached items when available.
     *
     * @param array<string, string> $sourceNames
     * @return list<array<string, mixed>>
     */
    private function fetchFeedItems(string $url, int $cacheLifetime, array $sourceNames): array
    {
        $cacheIdentifier = 'feed_' . md5($url);
        $feedData = $this->getCache()->get($cacheIdentifier);
        if ($feedData !== false) {
            return $feedData;
        }

        try {
            $response = $this->requestFactory->request($url, 'GET', [
                'headers' => [
                    'User-Agent' => 'MPC RSS TYPO3/13',
                    'Accept' => 'application/rss+xml, application/atom+xml;q=0.95, application/xml;q=0.9, */*;q=0.8',
                ],
                'timeout' => 10,
                'connect_timeout' => 6,
            ]);
        } catch (\Throwable $exception) {
            $this->logger?->warning('Fetching RSS feed failed', ['url' => $url, 'exception' => $exception]);
            $this->cacheNegativeResult($cacheIdentifier, $cacheLifetime);
            return [];
        }

        if ($response->getStatusCode() !== 200) {
            $this->logger?->warning('Fetching RSS feed returned unexpected status code', [
                'url' => $url,
                'statusCode' => $response->getStatusCode(),
            ]);
            $this->cacheNegativeResult($cacheIdentifier, $cacheLifetime);
            return [];
        }

        $body = (string)$response->getBody();
        if ($body === '') {
            $this->logger?->warning('Fetching RSS feed returned empty body', ['url' => $url]);
            $this->cacheNegativeResult($cacheIdentifier, $cacheLifetime);
            return [];
        }

        $previousErrors = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        if ($xml === false) {
            $this->logger?->warning('Failed to parse RSS feed XML', ['url' => $url]);
            $this->cacheNegativeResult($cacheIdentifier, $cacheLifetime);
            return [];
        }

        $feedItems = $this->getXmlChild($xml->channel ?? null, 'item') !== null
            ? $this->parseRssItems($xml, $url, $sourceNames)
            : $this->parseAtomItems($xml, $url, $sourceNames);

        if ($feedItems !== []) {
            $this->getCache()->set($cacheIdentifier, $feedItems, ['mpc_rss', 'mpc_rss_feed'], $cacheLifetime);
        }

        return $feedItems;
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

    private function cacheNegativeResult(string $cacheIdentifier, int $cacheLifetime): void
    {
        $this->getCache()->set($cacheIdentifier, [], ['mpc_rss', 'mpc_rss_feed_error'], min($cacheLifetime, 300));
    }

    /**
     * Strip unsafe tags and attributes from external feed HTML.
     * Allows only structural tags; <a> tags keep only href with http(s) scheme.
     */
    private function sanitizeHtml(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $safe = strip_tags($html, ['a', 'p', 'br', 'strong', 'em', 'b', 'i']);

        $safe = preg_replace('/<(p|br|strong|em|b|i)\s+[^>]*>/i', '<$1>', $safe) ?? $safe;

        $safe = preg_replace_callback(
            '/<a\s[^>]*>/i',
            static function (array $matches): string {
                if (preg_match('/href\s*=\s*"(https?:\/\/[^"]*)"/i', $matches[0], $m)) {
                    return '<a href="' . htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
                }
                if (preg_match('/href\s*=\s*\'(https?:\/\/[^\']*)\'/i', $matches[0], $m)) {
                    return '<a href="' . htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
                }
                return '<a>';
            },
            $safe,
        ) ?? $safe;

        return $safe;
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
