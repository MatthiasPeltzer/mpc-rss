<?php

declare(strict_types=1);

namespace Mpc\MpcRss\Service;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Log\LogManager;

class FeedService
{
    /**
     * Category detection patterns (German/English mapping)
     */
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
    private readonly VariableFrontend $cache;
    private readonly LoggerInterface $logger;

    public function __construct(CacheManager $cacheManager, private readonly RequestFactory $requestFactory)
    {
        $cache = $cacheManager->getCache('mpc_rss');
        if (!$cache instanceof VariableFrontend) {
            throw new \RuntimeException(sprintf(
                'Expected cache frontend "VariableFrontend" for identifier "%s", got "%s"',
                'mpc_rss',
                $cache::class
            ));
        }
        $this->cache = $cache;
        /** @var LoggerInterface $logger */
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $this->logger = $logger;
    }

    /**
     * @param list<string> $urls
     * @param array<string, string> $sourceNames URL => source name mapping
     * @return array<string, list<array<string,mixed>>> group => items
     */
    public function fetchGroupedByCategory(array $urls, int $maxItems, int $cacheLifetime, array $includeCategories = [], array $excludeCategories = [], array $sourceNames = [], string $groupingMode = 'category'): array
    {
        $items = [];
        foreach ($urls as $url) {
            $cacheIdentifier = 'feed_' . md5($url);
            $feedData = $this->cache->get($cacheIdentifier);
            if ($feedData === false) {
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
                    $this->logger->warning(
                        'Fetching RSS feed failed',
                        [
                            'url' => $url,
                            'exception' => $exception,
                        ]
                    );
                    // Cache a negative result briefly to avoid repeated failing requests
                    $this->cache->set(
                        $cacheIdentifier,
                        [],
                        ['mpc_rss', 'mpc_rss_feed_error'],
                        min($cacheLifetime, 300)
                    );
                    continue;
                }
                if ($response->getStatusCode() !== 200) {
                    $this->logger->warning(
                        'Fetching RSS feed returned unexpected status code',
                        [
                            'url' => $url,
                            'statusCode' => $response->getStatusCode(),
                        ]
                    );
                    $this->cache->set(
                        $cacheIdentifier,
                        [],
                        ['mpc_rss', 'mpc_rss_feed_error'],
                        min($cacheLifetime, 300)
                    );
                    continue;
                }
                $body = (string)$response->getBody();
                if ($body === '') {
                    $this->logger->warning(
                        'Fetching RSS feed returned empty body',
                        ['url' => $url]
                    );
                    $this->cache->set(
                        $cacheIdentifier,
                        [],
                        ['mpc_rss', 'mpc_rss_feed_error'],
                        min($cacheLifetime, 300)
                    );
                    continue;
                }
                $xml = @simplexml_load_string($body);
                $feedItems = [];
                $rssItems = $this->getXmlChild($xml->channel ?? null, 'item');
                if ($xml && $rssItems !== null) {
                    foreach ($rssItems as $entry) {
                        $title = $this->getXmlValue($entry, 'title');
                        $description = $this->getXmlValue($entry, 'description');
                        $link = $this->getXmlValue($entry, 'link');
                        $pubDate = $this->getXmlValue($entry, 'pubDate');
                        $dateIso = $this->parseDate($pubDate);

                        $categories = [];
                        $categoryElements = $this->getXmlChild($entry, 'category');
                        if ($categoryElements !== null) {
                            foreach ($categoryElements as $cat) {
                                $categories[] = (string)$cat;
                            }
                        }

                        // Extract image using helper method
                        $contentNs = $entry->children('http://purl.org/rss/1.0/modules/content/');
                        $encoded = $this->getXmlValue($contentNs, 'encoded');
                        $htmlSource = $encoded !== '' ? $encoded : $description;
                        $imageUrl = $this->extractImageUrl($entry, $htmlSource);

                        // Detect source name using helper method
                        $sourceName = $this->detectSourceName($url, $sourceNames);
                        $feedItems[] = [
                            'title' => strip_tags($title),
                            'description' => $description,
                            'link' => $link,
                            'date' => $dateIso,
                            'categories' => $categories,
                            'image' => $imageUrl,
                            'authors' => [],
                            'source' => $url,
                            'sourceName' => $sourceName,
                        ];
                    }
                } elseif ($xml) {
                    // Atom feed handling (http://www.w3.org/2005/Atom)
                    $xml->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
                    $entries = $xml->xpath('//atom:entry');
                    if (is_array($entries)) {
                        foreach ($entries as $entry) {
                            /** @var \SimpleXMLElement $entry */
                            $a = $entry->children('http://www.w3.org/2005/Atom');
                            $title = $this->getXmlValue($a, 'title');
                            $contentValue = $this->getXmlValue($a, 'content');
                            $summary = $this->getXmlValue($a, 'summary');
                            $description = $contentValue !== '' ? $contentValue : $summary;
                            $link = '';
                            $linkElements = $this->getXmlChild($a, 'link');
                            if ($linkElements !== null) {
                                foreach ($linkElements as $l) {
                                    $rel = isset($l['rel']) ? (string)$l['rel'] : '';
                                    $href = isset($l['href']) ? (string)$l['href'] : '';
                                    if ($href !== '' && ($rel === '' || $rel === 'alternate')) {
                                        $link = $href;
                                        if ($rel === 'alternate') {
                                            break;
                                        }
                                    }
                                }
                            }
                            if ($link === '' && $linkElements !== null && isset($linkElements[0]['href'])) {
                                $link = (string)$linkElements[0]['href'];
                            }
                            // Parse date (prefer updated over published)
                            $updated = $this->getXmlValue($a, 'updated');
                            $published = $this->getXmlValue($a, 'published');
                            $dateStr = $updated !== '' ? $updated : $published;
                            $dateIso = $this->parseDate($dateStr);

                            $categories = [];
                            $categoryElements = $this->getXmlChild($a, 'category');
                            if ($categoryElements !== null) {
                                foreach ($categoryElements as $cat) {
                                    $term = isset($cat['term']) ? (string)$cat['term'] : '';
                                    if ($term !== '') {
                                        $categories[] = $term;
                                    } else {
                                        $categories[] = (string)$cat;
                                    }
                                }
                            }

                            // Extract image using helper method
                            $imageUrl = $this->extractImageUrl($entry, $description);

                            // Detect source name using helper method
                            $sourceName = $this->detectSourceName($url, $sourceNames);
                            $feedItems[] = [
                                'title' => strip_tags($title),
                                'description' => $description,
                                'link' => $link,
                                'date' => $dateIso,
                                'categories' => $categories,
                                'image' => $imageUrl,
                                'authors' => [],
                                'source' => $url,
                                'sourceName' => $sourceName,
                            ];
                        }
                    }
                }
                // Only cache if we actually found items to avoid persisting empty failures
                if (!empty($feedItems)) {
                    $feedData = $feedItems;
                    // Add cache tags for better cache management
                    $tags = ['mpc_rss', 'mpc_rss_feed'];
                    $this->cache->set($cacheIdentifier, $feedData, $tags, $cacheLifetime);
                } else {
                    // Skip caching empty results to allow retry on next request
                    $feedData = [];
                }
            }
            $items = array_merge($items, $feedData);
        }

        // Deduplicate items by link to avoid showing same item multiple times
        $deduped = [];
        foreach ($items as $item) {
            $link = $item['link'] ?? '';
            if ($link !== '' && !isset($deduped[$link])) {
                $deduped[$link] = $item;
            } elseif ($link === '') {
                $deduped[] = $item;
            }
        }
        $items = array_values($deduped);

        // Apply grouping based on $groupingMode
        $grouped = [];
        $sourceNameMapping = []; // Track normalized -> display name mapping for source mode

        if ($groupingMode === 'none' || $groupingMode === 'date') {
            // Unified timeline or date-based grouping - sort by date first
            usort($items, static function ($a, $b) {
                return strcmp((string)($b['date'] ?? ''), (string)($a['date'] ?? ''));
            });

            if ($groupingMode === 'date') {
                // Group by date periods
                foreach ($items as $item) {
                    $dateStr = $item['date'] ?? '';
                    $groupKey = $this->getDateGroupKey($dateStr);
                    $grouped[$groupKey][] = $item;
                }
            } else {
                // No grouping - single unified timeline
                $grouped['Allgemein'] = $items;
            }
        } else {
            // Category or Source mode
            foreach ($items as $item) {
                $groupKey = 'Allgemein'; // Default group

                if ($groupingMode === 'category') {
                    $categories = $item['categories'];

                    // If no RSS categories, try to extract from URL or use source name
                    if (empty($categories)) {
                        $sourceUrl = $item['source'] ?? '';
                        $sourceName = $item['sourceName'] ?? '';

                        // Try to extract category from URL using helper method
                        $detectedCategory = $this->detectCategoryFromUrl($sourceUrl);

                        // Use detected category, source name, or "Allgemein" as fallback
                        $categories = $detectedCategory ? [$detectedCategory] : ($sourceName !== '' ? [$sourceName] : ['Allgemein']);
                    }

                    // apply include/exclude filters at item-level: if none of the item's categories pass, skip
                    $normalizedItemCategories = array_map('mb_strtolower', $categories);
                    $allow = true;
                    if ($includeCategories !== []) {
                        $normalizedInclude = array_map('mb_strtolower', $includeCategories);
                        $allow = (bool)array_intersect($normalizedItemCategories, $normalizedInclude);
                    }
                    if ($allow && $excludeCategories !== []) {
                        $normalizedExclude = array_map('mb_strtolower', $excludeCategories);
                        if ((bool)array_intersect($normalizedItemCategories, $normalizedExclude)) {
                            $allow = false;
                        }
                    }
                    if (!$allow) {
                        continue;
                    }

                    $groupKey = $categories[0] ?? 'Allgemein'; // Use first category
                } elseif ($groupingMode === 'source') {
                    $sourceName = $item['sourceName'] ?? 'Unbekannte Quelle';
                    // Normalize to lowercase for grouping, but preserve first occurrence for display
                    $normalizedKey = mb_strtolower($sourceName);
                    if (!isset($sourceNameMapping[$normalizedKey])) {
                        $sourceNameMapping[$normalizedKey] = $sourceName; // Store first occurrence
                    }
                    $groupKey = $normalizedKey;
                }

                $grouped[$groupKey][] = $item;
            }

            // For source mode, rename keys from normalized back to display names
            if ($groupingMode === 'source' && !empty($sourceNameMapping)) {
                $renamedGrouped = [];
                foreach ($grouped as $normalizedKey => $items) {
                    $displayName = $sourceNameMapping[$normalizedKey] ?? $normalizedKey;
                    $renamedGrouped[$displayName] = $items;
                }
                $grouped = $renamedGrouped;
            }
        }

        // sort each group by date desc and slice
        foreach ($grouped as &$groupItems) {
            usort($groupItems, static function ($a, $b) {
                return strcmp((string)($b['date'] ?? ''), (string)($a['date'] ?? ''));
            });
            if ($maxItems > 0) {
                $groupItems = array_slice($groupItems, 0, $maxItems);
            }
        }
        unset($groupItems);

        ksort($grouped, SORT_STRING | SORT_FLAG_CASE);
        return $grouped;
    }

    /**
     * Safely get SimpleXML child elements
     */
    private function getXmlChild(\SimpleXMLElement|null $element, string $property): \SimpleXMLElement|null
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

    /**
     * Safely access SimpleXML element property (PHP 8.1+ compatible)
     */
    private function getXmlValue(\SimpleXMLElement|null $element, string $property): string
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

    /**
     * Parse date string and return ISO 8601 format or null
     */
    private function parseDate(string $dateStr): ?string
    {
        if ($dateStr === '') {
            return null;
        }
        $timestamp = @strtotime($dateStr);
        if ($timestamp === false) {
            return null;
        }
        return date('c', $timestamp);
    }

    /**
     * Extract image URL from feed entry (supports enclosures, media:content, media:thumbnail, and HTML)
     */
    private function extractImageUrl(\SimpleXMLElement $entry, string $htmlContent = ''): ?string
    {
        // Try enclosure first
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

        // Try media namespace
        $mediaNs = $entry->children('http://search.yahoo.com/mrss/');

        // Try media:content
        $mediaContent = $this->getXmlChild($mediaNs, 'content');
        if ($mediaContent !== null) {
            foreach ($mediaContent as $mc) {
                $urlAttr = isset($mc['url']) ? (string)$mc['url'] : '';
                if ($urlAttr !== '') {
                    return $urlAttr;
                }
            }
        }

        // Try media:thumbnail
        $mediaThumbnail = $this->getXmlChild($mediaNs, 'thumbnail');
        if ($mediaThumbnail !== null) {
            $thumbUrl = isset($mediaThumbnail['url']) ? (string)$mediaThumbnail['url'] : '';
            if ($thumbUrl !== '') {
                return $thumbUrl;
            }
        }

        // Extract from HTML content
        if ($htmlContent !== '' && preg_match('/<img[^>]+src="([^"]+)"/i', $htmlContent, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Detect source name from URL or return default
     *
     * @param array<string, string> $sourceNames
     */
    private function detectSourceName(string $url, array $sourceNames): string
    {
        $sourceName = $sourceNames[$url] ?? null;
        if ($sourceName !== null && $sourceName !== '') {
            return $sourceName;
        }

        // Extract domain name from URL as fallback
        $parsedUrl = parse_url($url);
        if (isset($parsedUrl['host'])) {
            $host = $parsedUrl['host'];
            // Remove 'www.' prefix if present
            $host = preg_replace('/^www\./', '', $host);
            // Take first part of domain (e.g., "example" from "example.com")
            $parts = explode('.', $host);
            if (!empty($parts[0])) {
                return ucfirst($parts[0]);
            }
        }

        return 'RSS';
    }

    /**
     * Get date group key based on date string (for date grouping mode)
     */
    private function getDateGroupKey(string $dateStr): string
    {
        if ($dateStr === '') {
            return 'Kein Datum';
        }

        $timestamp = @strtotime($dateStr);
        if ($timestamp === false) {
            return 'Kein Datum';
        }

        $now = time();
        $daysDiff = (int)floor(($now - $timestamp) / 86400);

        return match (true) {
            $daysDiff === 0 => 'Heute',
            $daysDiff === 1 => 'Gestern',
            $daysDiff <= 7 => 'Diese Woche',
            $daysDiff <= 30 => 'Diesen Monat',
            $daysDiff <= 90 => 'Letzte 3 Monate',
            default => 'Älter',
        };
    }

    /**
     * Detect category from feed URL path
     */
    private function detectCategoryFromUrl(string $url): ?string
    {
        $pattern = '#/(' . implode('|', array_keys(self::CATEGORY_PATTERNS)) . ')(?:/|$)#i';
        if (preg_match($pattern, $url, $matches)) {
            $detected = ucfirst(strtolower($matches[1]));
            return self::CATEGORY_PATTERNS[strtolower($matches[1])] ?? $detected;
        }
        return null;
    }
}


