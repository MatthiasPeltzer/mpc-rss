<?php

declare(strict_types=1);

namespace Mpc\MpcRss\Tests\Acceptance\Backend;

use Mpc\MpcRss\Tests\Acceptance\Support\BackendTester;

final class ExtensionAcceptanceCest
{
    public function _before(BackendTester $I): void
    {
        $I->useExistingSession('admin');
    }

    public function feedPluginAppearsInContentWizard(BackendTester $I): void
    {
        $I->openPageLayoutModule(2);
        $I->openNewContentElementWizard();
        $I->seeNewRecordWizardItem('MPC RSS Feed', 'plugins');
    }
}
