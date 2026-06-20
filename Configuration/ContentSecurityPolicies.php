<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Directive;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Mutation;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationCollection;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationMode;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Scope;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\SourceScheme;
use TYPO3\CMS\Core\Type\Map;

// Feed item thumbnails are hot-linked from arbitrary third-party feed domains
// (see FeedService::extractImageUrl, restricted to http(s) by sanitizeUrl).
// Their hosts are not known ahead of time, so the only workable allow-list is
// the https scheme. We only *extend* img-src on the frontend scope; no other
// directive (script-src, style-src, …) is touched, so a site's CSP stays as
// strict as before for everything else.
return Map::fromEntries(
    [
        Scope::frontend(),
        new MutationCollection(
            new Mutation(
                MutationMode::Extend,
                Directive::ImgSrc,
                SourceScheme::https,
            ),
        ),
    ],
);
