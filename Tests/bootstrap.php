<?php

declare(strict_types=1);

/**
 * Bootstrap for the mpc_rss unit test suite.
 *
 * The extension can be tested either standalone (its own `composer install`,
 * which uses the `.Build/vendor` vendor-dir configured in composer.json) or from
 * inside the mpcore monorepo where it is symlinked into the project's vendor
 * directory via a Composer path repository.
 */
$autoloadCandidates = [
    __DIR__ . '/../.Build/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
];

foreach ($autoloadCandidates as $autoload) {
    if (is_file($autoload)) {
        require $autoload;

        return;
    }
}

fwrite(
    STDERR,
    "Unable to locate a Composer autoload.php for the mpc_rss test suite.\n"
    . "Run `composer install` in the extension, or run the suite from the mpcore monorepo.\n"
);
exit(1);
