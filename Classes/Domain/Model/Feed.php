<?php

declare(strict_types=1);

namespace Mpc\MpcRss\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Feed extends AbstractEntity
{
    protected int $sysLanguageUid = 0;
    protected int $l10nParent = 0;
    protected int $ttContent = 0;
    protected string $title = '';
    protected string $feedUrl = '';
    protected string $sourceName = '';
    protected string $description = '';
    protected bool $hidden = false;

    public function getTtContent(): int
    {
        return $this->ttContent;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getFeedUrl(): string
    {
        return $this->feedUrl;
    }

    public function getSourceName(): string
    {
        return $this->sourceName;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }
}
