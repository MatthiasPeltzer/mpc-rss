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

    public function getSysLanguageUid(): int
    {
        return $this->sysLanguageUid;
    }

    public function setSysLanguageUid(int $sysLanguageUid): void
    {
        $this->sysLanguageUid = $sysLanguageUid;
    }

    public function getL10nParent(): int
    {
        return $this->l10nParent;
    }

    public function setL10nParent(int $l10nParent): void
    {
        $this->l10nParent = $l10nParent;
    }

    public function getTtContent(): int
    {
        return $this->ttContent;
    }

    public function setTtContent(int $ttContent): void
    {
        $this->ttContent = $ttContent;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getFeedUrl(): string
    {
        return $this->feedUrl;
    }

    public function setFeedUrl(string $feedUrl): void
    {
        $this->feedUrl = $feedUrl;
    }

    public function getSourceName(): string
    {
        return $this->sourceName;
    }

    public function setSourceName(string $sourceName): void
    {
        $this->sourceName = $sourceName;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): void
    {
        $this->hidden = $hidden;
    }
}

