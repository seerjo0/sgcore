<?php

namespace Tests\Feature;

use App\Modules\Core\Providers\CoreServiceProvider;
use App\Modules\Core\Services\InstallerLock;
use App\Modules\Core\Services\Settings;
use Tests\TestCase;

class ModuleLoaderTest extends TestCase
{
    public function test_module_service_providers_are_registered(): void
    {
        $this->assertTrue($this->app->providerIsLoaded(CoreServiceProvider::class));
    }

    public function test_module_services_resolve_from_the_container(): void
    {
        $this->assertInstanceOf(Settings::class, $this->app->make(Settings::class));
        $this->assertInstanceOf(InstallerLock::class, $this->app->make(InstallerLock::class));
    }

    public function test_module_configuration_is_merged(): void
    {
        $this->assertArrayHasKey('installer', config('cms'));
        $this->assertNotEmpty(config('cms.installer.lock_file'));
    }
}
