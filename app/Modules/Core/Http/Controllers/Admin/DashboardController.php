<?php

namespace App\Modules\Core\Http\Controllers\Admin;

use App\Models\User;
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
        ]);
    }
}
