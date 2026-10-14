<?php

namespace App\Modules\Core\Http\Controllers\Admin;

use App\Modules\Core\Services\UpdateChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UpdateController extends Controller
{
    /**
     * Force-refresh the cached release info and report the outcome. Does not
     * apply anything — this is a read-only check.
     */
    public function check(UpdateChecker $checker): RedirectResponse
    {
        if (! config('cms.updater.enabled')) {
            return back()->with('status', __('updater.disabled'));
        }

        $update = $checker->updateAvailable(mayFetch: true);

        if ($update === null) {
            return back()->with('status', __('updater.up_to_date', ['version' => $checker->currentVersion()]));
        }

        return back()->with('status', __('updater.available', ['version' => $update['tag']]));
    }

    /**
     * Hide the update banner for a specific version until a newer one appears.
     */
    public function dismiss(Request $request): RedirectResponse
    {
        $request->validate(['tag' => ['required', 'string', 'max:64']]);

        session(['update_dismissed' => (string) $request->input('tag')]);

        return back();
    }
}
