<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Contracts\InstallStep;
use Illuminate\Contracts\Foundation\Application;

class InstallStepRegistry
{
    public function __construct(private Application $app) {}

    /**
     * All registered install steps, sorted by order.
     *
     * @return array<int, InstallStep>
     */
    public function all(): array
    {
        return collect($this->app->tagged('cms.install_steps'))
            ->sort(fn (InstallStep $first, InstallStep $second): int => $first->order() <=> $second->order())
            ->values()
            ->all();
    }
}
