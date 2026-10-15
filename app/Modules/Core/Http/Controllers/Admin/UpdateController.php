<?php

namespace App\Modules\Core\Http\Controllers\Admin;

use App\Modules\Core\Services\UpdateChecker;
use App\Modules\Core\Services\UpdateRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RuntimeException;

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

        if ($update !== null) {
            return back()->with('status', __('updater.available', ['version' => $update['tag']]));
        }

        if ($checker->latest() === null && ($error = $checker->lastError()) !== null) {
            return back()->with('error', __('updater.check_failed', ['message' => $error]));
        }

        return back()->with('status', __('updater.up_to_date', ['version' => $checker->currentVersion()]));
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

    /**
     * "One-click" apply, behind explicit opt-in + typed confirmation. Runs the
     * same flow as `php artisan sgcore:update` (maintenance + backup + git
     * checkout + composer + migrate, with rollback on failure).
     */
    public function apply(Request $request, UpdateChecker $checker, UpdateRunner $runner): RedirectResponse
    {
        $data = $request->validate([
            'confirm' => ['required', 'string'],
        ]);

        if (! config('cms.updater.apply.enabled')) {
            return back()->with('error', __('updater.apply_disabled'));
        }

        $update = $checker->updateAvailable(mayFetch: true);

        if ($update === null) {
            return back()->with('status', __('updater.up_to_date', ['version' => $checker->currentVersion()]));
        }

        if ((string) $data['confirm'] !== $update['tag']) {
            return back()->with('error', __('updater.confirm_mismatch'));
        }

        $blockers = $runner->blockers();

        if ($blockers !== []) {
            return back()->with('error', __('updater.blocked', ['reasons' => implode(' · ', $blockers)]));
        }

        @set_time_limit(0);

        try {
            $runner->apply($update['tag']);
        } catch (RuntimeException $e) {
            report($e);

            return back()->with('error', __('updater.apply_failed', ['message' => $e->getMessage()]));
        }

        return back()->with('success', __('updater.applied', ['version' => $update['tag']]));
    }
}
