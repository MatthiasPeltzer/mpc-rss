<?php

declare(strict_types=1);

namespace Mpc\MpcRss\Tests\Acceptance\Backend;

use Mpc\MpcRss\Tests\Acceptance\Support\BackendTester;

final class SmokeCest
{
    public function _before(BackendTester $I): void
    {
        $I->useExistingSession('admin');
    }

    public function backendToolbarIsVisible(BackendTester $I): void
    {
        $I->seeElement('.t3js-scaffold-toolbar');
    }
}
