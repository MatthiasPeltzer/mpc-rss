<?php

declare(strict_types=1);

namespace Mpc\MpcRss\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\Repository;

class FeedRepository extends Repository
{
    public function initializeObject(): void
    {
        $querySettings = $this->createQuery()->getQuerySettings();
        // Don't respect storage page, fetch from anywhere
        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * Find feeds by tt_content uid (parent content element)
     *
     * @param int $contentUid
     * @return array|\TYPO3\CMS\Extbase\Persistence\QueryResultInterface
     */
    public function findByContentElement(int $contentUid)
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching(
            $query->equals('ttContent', $contentUid)
        );
        return $query->execute();
    }
}

