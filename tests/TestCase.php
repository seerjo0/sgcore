<?php

namespace Tests;

use App\Modules\Core\Services\InstallerLock;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Mark the application as installed using a lock file in the system temp dir.
     */
    protected function fakeInstalled(): void
    {
        config(['cms.installer.lock_file' => sys_get_temp_dir().'/sgcore-test-'.uniqid('', true).'.lock']);

        $this->app->make(InstallerLock::class)->create(['site_name' => 'Teste']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
