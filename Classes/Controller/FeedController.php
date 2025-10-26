<?php

declare(strict_types=1);

namespace Mpc\MpcRss\Controller;

use Doctrine\DBAL\ParameterType;
use Mpc\MpcRss\Domain\Repository\FeedRepository;
use Mpc\MpcRss\Service\FeedService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class FeedController extends ActionController
{
    public function __construct(
        private readonly FeedService $feedService,
        private readonly FeedRepository $feedRepository
    ) {
    }

    /**
     * Parse comma-separated string into trimmed array
     */
    private function parseCommaSeparated(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    /**
     * Get setting from TCA field or fallback to TypoScript
     */
    private function getSetting(array $data, string $tcaField, string $settingKey, mixed $default): mixed
    {
        return $data[$tcaField] ?? ($this->settings[$settingKey] ?? $default);
    }

    /**
     * Fetch feed URLs and source names from database
     * 
     * @return array{0: array<string>, 1: array<string, string>} [urls, sourceNames]
     */
    private function fetchFeedUrlsAndSourceNames(int $contentUid): array
    {
        $urls = [];
        $sourceNames = [];
        
        if ($contentUid <= 0) {
            return [$urls, $sourceNames];
        }

        $feeds = $this->feedRepository->findByContentElement($contentUid);
        $feedCount = is_countable($feeds) ? count($feeds) : (method_exists($feeds, 'count') ? $feeds->count() : 0);
        
        // If no feeds found via repository, try direct database query as fallback
        if ($feedCount === 0) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('tx_mpcrss_domain_model_feed');
            $feedRecords = $queryBuilder
                ->select('*')
                ->from('tx_mpcrss_domain_model_feed')
                ->where(
                    $queryBuilder->expr()->eq('tt_content', $queryBuilder->createNamedParameter($contentUid, ParameterType::INTEGER)),
                    $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
                    $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER))
                )
                ->orderBy('sorting', 'ASC')
                ->executeQuery()
                ->fetchAllAssociative();
            
            foreach ($feedRecords as $record) {
                if (!empty($record['feed_url'])) {
                    $feedUrl = $record['feed_url'];
                    $urls[] = $feedUrl;
                    if (!empty($record['source_name'])) {
                        $sourceNames[$feedUrl] = $record['source_name'];
                    }
                }
            }
        } else {
            // Use repository results
            foreach ($feeds as $feed) {
                if (!$feed->isHidden() && $feed->getFeedUrl() !== '') {
                    $feedUrl = $feed->getFeedUrl();
                    $urls[] = $feedUrl;
                    if ($feed->getSourceName() !== '') {
                        $sourceNames[$feedUrl] = $feed->getSourceName();
                    }
                }
            }
        }
        
        return [$urls, $sourceNames];
    }

    public function listAction(): ResponseInterface
    {
        // Read from TCA fields (data array) or fallback to TypoScript settings
        $currentContentObject = $this->request->getAttribute('currentContentObject');
        $data = $currentContentObject?->data ?? [];
        $contentUid = (int)($data['uid'] ?? 0);
        
        $maxItems = (int)$this->getSetting($data, 'tx_mpcrss_max_items', 'maxItems', 9);
        $cacheLifetime = (int)$this->getSetting($data, 'tx_mpcrss_cache_lifetime', 'cacheLifetime', 1800);
        $include = $this->parseCommaSeparated((string)$this->getSetting($data, 'tx_mpcrss_include_categories', 'includeCategories', ''));
        $exclude = $this->parseCommaSeparated((string)$this->getSetting($data, 'tx_mpcrss_exclude_categories', 'excludeCategories', ''));
        $showFilterSetting = $this->getSetting($data, 'tx_mpcrss_show_filter', 'showCategoryFilter', '');
        $showFilter = $showFilterSetting === '' ? true : (bool)$showFilterSetting;
        $paginate = (bool)$this->getSetting($data, 'tx_mpcrss_paginate', 'paginate', false);
        $paginateCategory = (string)($this->settings['paginateCategory'] ?? '');
        $itemsPerPage = max(1, (int)$this->getSetting($data, 'tx_mpcrss_items_per_page', 'itemsPerPage', 10));
        $defaultCategory = (string)$this->getSetting($data, 'tx_mpcrss_default_category', 'defaultCategory', 'Politik');
        $groupingMode = (string)$this->getSetting($data, 'tx_mpcrss_grouping_mode', 'groupingMode', 'category');

        // Fetch feeds from database (inline records)
        [$urls, $sourceNames] = $this->fetchFeedUrlsAndSourceNames($contentUid);

        // No fallback - requires configured feeds
        // Users must add feeds via the backend inline records

        $grouped = $this->feedService->fetchGroupedByCategory($urls, $maxItems, $cacheLifetime, $include, $exclude, $sourceNames, $groupingMode);

        // Store all categories for navigation BEFORE filtering
        $allCategories = array_keys($grouped);
        $filterCategory = $this->request->hasArgument('filterCategory') ? (string)$this->request->getArgument('filterCategory') : '';
        
        // Determine active category: explicit filter > pagination category > default category > first available
        $activeCategory = '';
        if ($filterCategory !== '') {
            $activeCategory = $filterCategory;
        } elseif ($paginateCategory !== '') {
            $activeCategory = $paginateCategory;
        } else {
            // Use default category if configured and exists, otherwise first category
            if ($defaultCategory !== '' && in_array($defaultCategory, $allCategories, true)) {
                $activeCategory = $defaultCategory;
            } else {
                $activeCategory = $allCategories[0] ?? '';
            }
        }
        
        // Adjust navigation based on grouping mode
        $navigationLabel = $this->getNavigationLabel($groupingMode);
        $showNavigation = $groupingMode !== 'none' && $showFilter;

        $page = max(1, (int)($this->request->hasArgument('page') ? $this->request->getArgument('page') : 1));
        $pagination = null;
        $pages = [];
        
        // Handle pagination BEFORE filtering grouped array
        if ($paginate && $activeCategory !== '' && isset($grouped[$activeCategory])) {
            $total = count($grouped[$activeCategory]);
            $numPages = (int)ceil($total / $itemsPerPage);
            $offset = ($page - 1) * $itemsPerPage;
            $grouped[$activeCategory] = array_slice($grouped[$activeCategory], $offset, $itemsPerPage);
            $pagination = [
                'activeCategory' => $activeCategory,
                'page' => $page,
                'numPages' => $numPages,
                'itemsPerPage' => $itemsPerPage,
                'total' => $total,
            ];
            $pages = range(1, $numPages);
        }

        // Filter to active category only (keeping all categories for navigation)
        if ($activeCategory !== '' && isset($grouped[$activeCategory])) {
            $grouped = [$activeCategory => $grouped[$activeCategory]];
        }

        $this->view->assignMultiple([
            'grouped' => $grouped,
            'categories' => $allCategories, // Pass all categories for navigation
            'activeCategory' => $activeCategory,
            'showFilter' => $showNavigation,
            'paginate' => $paginate,
            'pagination' => $pagination,
            'pages' => $pages,
            'groupingMode' => $groupingMode,
            'navigationLabel' => $navigationLabel,
            'settings' => [
                'maxItems' => $maxItems,
                'cacheLifetime' => $cacheLifetime,
                'feedUrls' => $urls,
                'includeCategories' => $include,
                'excludeCategories' => $exclude,
                'itemsPerPage' => $itemsPerPage,
                'groupingMode' => $groupingMode,
            ],
        ]);
        return $this->htmlResponse();
    }

    /**
     * Get navigation label based on grouping mode
     */
    private function getNavigationLabel(string $mode): string
    {
        return match ($mode) {
            'category' => 'Categories',
            'source' => 'Sources',
            'date' => 'Time Periods',
            'none' => '',
            default => 'Filter',
        };
    }
}


