<?php

declare(strict_types=1);

namespace Mpc\MpcRss\Domain\Repository;

use Mpc\MpcRss\Domain\Model\Feed;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @extends Repository<Feed>
 */
class FeedRepository extends Repository
{
    public function initializeObject(): void
    {
        $querySettings = $this->createQuery()->getQuerySettings();
        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * @return QueryResultInterface<Feed>
     */
    public function findByContentElement(int $contentUid): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->matching(
            $query->equals('ttContent', $contentUid)
        );
        return $query->execute();
    }
}
