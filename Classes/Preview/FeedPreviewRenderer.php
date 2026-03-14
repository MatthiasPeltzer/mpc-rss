<?php

declare(strict_types=1);

namespace Mpc\MpcRss\Preview;

use TYPO3\CMS\Backend\Preview\StandardContentPreviewRenderer;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem;
use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FeedPreviewRenderer extends StandardContentPreviewRenderer
{
    private const LLL = 'LLL:EXT:mpc_rss/Resources/Private/Language/locallang_db.xlf:';

    private const GROUPING_LABELS = [
        'category' => 'tt_content.tx_mpcrss_grouping_mode.category',
        'source' => 'tt_content.tx_mpcrss_grouping_mode.source',
        'date' => 'tt_content.tx_mpcrss_grouping_mode.date',
        'none' => 'tt_content.tx_mpcrss_grouping_mode.none',
    ];

    public function renderPageModulePreviewContent(GridColumnItem $item): string
    {
        $recordOrObject = $item->getRecord();
        $record = is_array($recordOrObject) ? $recordOrObject : $recordOrObject->toArray();
        $uid = (int)($record['uid'] ?? 0);

        if ($uid <= 0) {
            return '';
        }

        $feeds = $this->fetchFeeds($uid);
        $groupingMode = (string)($record['tx_mpcrss_grouping_mode'] ?? 'category');
        $maxItems = (int)($record['tx_mpcrss_max_items'] ?? 9);
        $cacheLifetime = (int)($record['tx_mpcrss_cache_lifetime'] ?? 1800);
        $defaultCategory = (string)($record['tx_mpcrss_default_category'] ?? '');
        $include = trim((string)($record['tx_mpcrss_include_categories'] ?? ''));
        $exclude = trim((string)($record['tx_mpcrss_exclude_categories'] ?? ''));
        $showFilter = (bool)($record['tx_mpcrss_show_filter'] ?? true);
        $paginate = (bool)($record['tx_mpcrss_paginate'] ?? false);
        $itemsPerPage = (int)($record['tx_mpcrss_items_per_page'] ?? 10);

        $html = '<div style="padding:4px 0">';
        $html .= $this->renderFeedList($feeds);
        $html .= $this->renderSettingsTable(
            $groupingMode,
            $maxItems,
            $cacheLifetime,
            $defaultCategory,
            $include,
            $exclude,
            $showFilter,
            $paginate,
            $itemsPerPage,
        );
        $html .= '</div>';

        return $html;
    }

    /**
     * @return list<array{title: string, feed_url: string, source_name: string, hidden: int}>
     */
    private function fetchFeeds(int $contentUid): array
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_mpcrss_domain_model_feed');
        $qb->getRestrictions()->removeAll();

        return $qb
            ->select('title', 'feed_url', 'source_name', 'hidden')
            ->from('tx_mpcrss_domain_model_feed')
            ->where(
                $qb->expr()->eq('tt_content', $qb->createNamedParameter($contentUid, ParameterType::INTEGER)),
                $qb->expr()->eq('deleted', $qb->createNamedParameter(0, ParameterType::INTEGER)),
            )
            ->orderBy('sorting')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    private function renderFeedList(array $feeds): string
    {
        if ($feeds === []) {
            return '<p class="text-body-secondary mb-1"><em>' . $this->ll('preview.noFeeds') . '</em></p>';
        }

        $html = '<table class="table table-sm table-borderless mb-2" style="font-size:11px">';
        $html .= '<thead><tr class="text-body-secondary" style="border-bottom:1px solid var(--bs-border-color)">'
            . '<th class="fw-semibold" style="padding:3px 6px">' . $this->ll('preview.feed') . '</th>'
            . '<th class="fw-semibold" style="padding:3px 6px">' . $this->ll('preview.url') . '</th>'
            . '</tr></thead><tbody>';

        foreach ($feeds as $feed) {
            $hidden = (int)($feed['hidden'] ?? 0);
            $opacity = $hidden ? ' style="opacity:.45"' : '';
            $title = $this->esc($feed['title'] ?: $this->ll('preview.untitled'));
            $source = $feed['source_name'] !== ''
                ? ' <span class="text-body-secondary">(' . $this->esc($feed['source_name']) . ')</span>'
                : '';
            $url = $this->esc($this->truncateUrl($feed['feed_url']));
            $hiddenBadge = $hidden
                ? ' <span class="text-danger fw-bold" title="' . $this->esc($this->ll('preview.hidden')) . '">[H]</span>'
                : '';

            $html .= '<tr' . $opacity . ' style="border-bottom:1px solid var(--bs-border-color-translucent)">'
                . '<td style="padding:2px 6px">' . $title . $source . $hiddenBadge . '</td>'
                . '<td class="text-body-secondary" style="padding:2px 6px;word-break:break-all">' . $url . '</td>'
                . '</tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }

    private function renderSettingsTable(
        string $groupingMode,
        int $maxItems,
        int $cacheLifetime,
        string $defaultCategory,
        string $include,
        string $exclude,
        bool $showFilter,
        bool $paginate,
        int $itemsPerPage,
    ): string {
        $modeKey = self::GROUPING_LABELS[$groupingMode] ?? null;
        $modeLabel = $modeKey !== null ? $this->ll($modeKey) : $groupingMode;
        $cacheMinutes = (int)round($cacheLifetime / 60);

        $yes = $this->ll('preview.yes');
        $no = $this->ll('preview.no');

        $rows = [
            [$this->ll('preview.grouping'), $modeLabel, ''],
            [$this->ll('preview.maxItems'), (string)$maxItems, ''],
            [$this->ll('preview.cache'), sprintf($this->ll('preview.minutes'), $cacheMinutes), ''],
        ];

        if ($defaultCategory !== '') {
            $rows[] = [$this->ll('preview.default'), $this->esc($defaultCategory), ''];
        }
        if ($include !== '') {
            $rows[] = [$this->ll('preview.include'), $this->esc($include), 'text-success'];
        }
        if ($exclude !== '') {
            $rows[] = [$this->ll('preview.exclude'), $this->esc($exclude), 'text-danger'];
        }

        $rows[] = [$this->ll('preview.filterNav'), $showFilter ? $yes : $no, ''];

        if ($paginate) {
            $rows[] = [$this->ll('preview.pagination'), sprintf($this->ll('preview.perPage'), $itemsPerPage), ''];
        }

        $html = '<table style="font-size:11px">';
        foreach ($rows as [$label, $value, $valueClass]) {
            $cls = $valueClass !== '' ? ' class="' . $valueClass . '"' : '';
            $html .= '<tr>'
                . '<td class="text-body-secondary" style="padding:1px 8px 1px 0;white-space:nowrap">' . $label . '</td>'
                . '<td' . $cls . ' style="padding:1px 0">' . $value . '</td>'
                . '</tr>';
        }
        $html .= '</table>';

        return $html;
    }

    private function truncateUrl(string $url): string
    {
        if (mb_strlen($url) <= 60) {
            return $url;
        }
        return mb_substr($url, 0, 57) . '...';
    }

    private function ll(string $key): string
    {
        return $this->getLanguageService()->sL(self::LLL . $key) ?: $key;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
