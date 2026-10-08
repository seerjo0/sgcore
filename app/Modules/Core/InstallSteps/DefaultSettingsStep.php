<?php

namespace App\Modules\Core\InstallSteps;

use App\Modules\Core\Contracts\InstallStep;
use App\Modules\Core\Services\Settings;

class DefaultSettingsStep implements InstallStep
{
    public function __construct(private Settings $settings) {}

    public function name(): string
    {
        return 'core.settings';
    }

    public function order(): int
    {
        return 10;
    }

    public function run(array $context): void
    {
        $this->settings->set(
            'site_name',
            (string) ($context['site_name'] ?? config('app.name')),
            'general',
        );

        $this->settings->set('seo_title', '', 'seo');
        $this->settings->set('seo_description', '', 'seo');
        $this->settings->set('active_theme', (string) config('cms.themes.default_theme', 'classic'), 'general');
    }
}
