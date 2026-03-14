<?php

declare(strict_types=1);

namespace Mpc\MpcRss\Controller;

use Mpc\MpcRss\Domain\Repository\FeedRepository;
use Mpc\MpcRss\Service\FeedService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class FeedController extends ActionController
{
    private const EXT_NAME = 'MpcRss';

    /**
     * Keys that FeedService uses for generated groups (date mode, default fallback).
     * Maps each English key to its XLF translation id.
     */
    private const GROUP_LABEL_KEYS = [
        'General' => 'group.default',
        'Unknown Source' => 'group.unknownSource',
        'No Date' => 'group.noDate',
        'Today' => 'group.today',
        'Yesterday' => 'group.yesterday',
        'This Week' => 'group.thisWeek',
        'This Month' => 'group.thisMonth',
        'Last 3 Months' => 'group.last3Months',
        'Older' => 'group.older',
    ];

    public function __construct(
        private readonly FeedService $feedService,
        private readonly FeedRepository $feedRepository,
    ) {}

    /**
     * @return list<string>
     */
    private function parseCommaSeparated(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    private function getSetting(array $data, string $tcaField, string $settingKey, mixed $default): mixed
    {
        return $data[$tcaField] ?? ($this->settings[$settingKey] ?? $default);
    }

    /**
     * @return array{0: list<string>, 1: array<string, string>}
     */
    private function fetchFeedUrlsAndSourceNames(int $contentUid): array
    {
        $urls = [];
        $sourceNames = [];

        if ($contentUid <= 0) {
            return [$urls, $sourceNames];
        }

        foreach ($this->feedRepository->findByContentElement($contentUid) as $feed) {
            if (!$feed->isHidden() && $feed->getFeedUrl() !== '') {
                $feedUrl = $feed->getFeedUrl();
                $urls[] = $feedUrl;
                if ($feed->getSourceName() !== '') {
                    $sourceNames[$feedUrl] = $feed->getSourceName();
                }
            }
        }

        return [$urls, $sourceNames];
    }

    /**
     * Translate known group keys (from FeedService) to the current frontend language.
     * Keys that originate from RSS feeds (actual category names) pass through unchanged.
     *
     * @param array<string, list<array<string, mixed>>> $grouped
     * @return array<string, list<array<string, mixed>>>
     */
    private function translateGroupKeys(array $grouped): array
    {
        $translated = [];
        foreach ($grouped as $key => $items) {
            if (isset(self::GROUP_LABEL_KEYS[$key])) {
                $label = LocalizationUtility::translate(self::GROUP_LABEL_KEYS[$key], self::EXT_NAME) ?? $key;
                $translated[$label] = $items;
            } else {
                $translated[$key] = $items;
            }
        }
        return $translated;
    }

    public function listAction(): ResponseInterface
    {
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

        [$urls, $sourceNames] = $this->fetchFeedUrlsAndSourceNames($contentUid);

        $grouped = $this->feedService->fetchGroupedByCategory($urls, $maxItems, $cacheLifetime, $include, $exclude, $sourceNames, $groupingMode);
        $grouped = $this->translateGroupKeys($grouped);

        $allCategories = array_keys($grouped);
        $filterCategory = $this->request->hasArgument('filterCategory') ? (string)$this->request->getArgument('filterCategory') : '';

        $activeCategory = '';
        if ($filterCategory !== '') {
            $activeCategory = $filterCategory;
        } elseif ($paginateCategory !== '') {
            $activeCategory = $paginateCategory;
        } else {
            if ($defaultCategory !== '' && in_array($defaultCategory, $allCategories, true)) {
                $activeCategory = $defaultCategory;
            } else {
                $activeCategory = $allCategories[0] ?? '';
            }
        }

        $navigationLabel = $this->getNavigationLabel($groupingMode);
        $showNavigation = $groupingMode !== 'none' && $showFilter;

        $page = max(1, (int)($this->request->hasArgument('page') ? $this->request->getArgument('page') : 1));
        $pagination = null;
        $pages = [];

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

        if ($activeCategory !== '' && isset($grouped[$activeCategory])) {
            $grouped = [$activeCategory => $grouped[$activeCategory]];
        }

        $this->view->assignMultiple([
            'grouped' => $grouped,
            'categories' => $allCategories,
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

    private function getNavigationLabel(string $mode): string
    {
        return match ($mode) {
            'category' => LocalizationUtility::translate('navigation.categories', self::EXT_NAME) ?? 'Categories',
            'source' => LocalizationUtility::translate('navigation.sources', self::EXT_NAME) ?? 'Sources',
            'date' => LocalizationUtility::translate('navigation.timePeriods', self::EXT_NAME) ?? 'Time Periods',
            'none' => '',
            default => LocalizationUtility::translate('navigation.filter', self::EXT_NAME) ?? 'Filter',
        };
    }
}
