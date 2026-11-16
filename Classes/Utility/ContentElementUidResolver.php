<?php

declare(strict_types=1);

namespace Mpc\MpcRss\Utility;

/**
 * Resolves the tt_content uid used to load inline feed records.
 *
 * On translated frontend content, {@see ContentObjectRenderer} keeps the default
 * language row in {@code uid} and exposes the overlay in {@code _LOCALIZED_UID}.
 * Inline feed rows are stored against the localized content element uid.
 */
final class ContentElementUidResolver
{
    /**
     * @param array<string, mixed> $contentData ContentObjectRenderer row data
     */
    public static function resolve(array $contentData): int
    {
        $localizedUid = (int)($contentData['_LOCALIZED_UID'] ?? 0);
        if ($localizedUid > 0) {
            return $localizedUid;
        }

        return (int)($contentData['uid'] ?? 0);
    }
}
