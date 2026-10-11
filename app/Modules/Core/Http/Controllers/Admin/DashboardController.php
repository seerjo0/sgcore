<?php

namespace App\Modules\Core\Http\Controllers\Admin;

use App\Models\User;
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
    public function index(): View
    {
        return view('core::admin.dashboard', [
            'userCount' => User::query()->count(),
            'pageCount' => Page::query()->count(),
            'mediaCount' => Medium::query()->count(),
            'menuCount' => Menu::query()->count(),
        ]);
    }
}
