<?php

namespace App\Modules\Core\Http\Controllers\Admin;

use App\Models\User;
use App\Modules\Core\Services\UpdateChecker;
use App\Modules\Media\Models\Medium;
use App\Modules\Pages\Models\Menu;
use App\Modules\Pages\Models\Page;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Render the admin dashboard.
     */
    public function index(UpdateChecker $updater): View
    {
        // Seed the cached latest-release (fail-soft, cached for the TTL) so the
        // site-wide update banner is populated; the layout reads it without
        // hitting GitHub again.
        $updater->updateAvailable(mayFetch: true);

        return view('core::admin.dashboard', [
            'userCount' => User::query()->count(),
            'pageCount' => Page::query()->count(),
            'mediaCount' => Medium::query()->count(),
            'menuCount' => Menu::query()->count(),
        ]);
    }
}
