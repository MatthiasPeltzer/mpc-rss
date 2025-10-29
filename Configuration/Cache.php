<?php

use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;

return [
    'mpc_rss' => [
        'frontend' => VariableFrontend::class,
        'backend' => Typo3DatabaseBackend::class,
        'groups' => ['mpc_rss'], // Custom group - only clears when explicitly targeted
        'options' => [
            'compression' => true,
            'defaultLifetime' => 600, // Default 10 minutes
        ],
    ],
];


