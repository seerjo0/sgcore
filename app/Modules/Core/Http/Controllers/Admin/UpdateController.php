<?php

namespace App\Modules\Core\Http\Controllers\Admin;

use App\Modules\Core\Services\PackageSource;
use App\Modules\Core\Services\PackageUpdater;
use App\Modules\Core\Services\UpdateChecker;
use App\Modules\Core\Services\UpdateRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Throwable;

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

    /**
     * WordPress-style update from an uploaded package .zip (shared hosting: no
     * git/composer/node needed, just file writes + migrate).
     */
    public function applyPackage(Request $request, UpdateChecker $checker, PackageUpdater $updater): RedirectResponse
    {
        $data = $request->validate([
            'package' => ['required', 'file', 'mimes:zip', 'max:'.(int) (config('cms.updater.apply.max_package_bytes', 200 * 1024 * 1024) / 1024)],
            'confirm' => ['required', 'string'],
        ]);

        if (! config('cms.updater.apply.enabled') || ! config('cms.updater.apply.package', true)) {
            return back()->with('error', __('updater.package_disabled'));
        }

        $file = $request->file('package');

        try {
            $info = $updater->inspect($file->getRealPath());
        } catch (RuntimeException $e) {
            return back()->with('error', __('updater.apply_failed', ['message' => $e->getMessage()]));
        }

        if ((string) $data['confirm'] !== $info['version']) {
            return back()->with('error', __('updater.confirm_mismatch'));
        }

        return $this->runPackageUpdate($updater, $file->getRealPath(), $checker, $info['version']);
    }

    /**
     * Download the release's package asset and apply it (one-click, opt-in).
     */
    public function downloadPackage(
        Request $request,
        UpdateChecker $checker,
        PackageUpdater $updater,
        PackageSource $source,
    ): RedirectResponse {
        $data = $request->validate(['confirm' => ['required', 'string']]);

        if (! config('cms.updater.apply.enabled')
            || ! config('cms.updater.apply.package', true)
            || ! config('cms.updater.apply.download', true)) {
            return back()->with('error', __('updater.package_disabled'));
        }

        $update = $checker->updateAvailable(mayFetch: true);

        if ($update === null) {
            return back()->with('status', __('updater.up_to_date', ['version' => $checker->currentVersion()]));
        }

        if ((string) $data['confirm'] !== ($update['version'] ?? $update['tag'])) {
            return back()->with('error', __('updater.confirm_mismatch'));
        }

        $asset = $checker->packageAsset($update);

        if ($asset === null) {
            return back()->with('error', __('updater.package_no_asset'));
        }

        try {
            $downloaded = $source->fetch($asset);
        } catch (RuntimeException $e) {
            return back()->with('error', __('updater.apply_failed', ['message' => $e->getMessage()]));
        }

        try {
            return $this->runPackageUpdate($updater, $downloaded['path'], $checker, $update['version']);
        } finally {
            @unlink($downloaded['path']);
        }
    }

    /**
     * Shared package apply flow: maintenance mode, in-place update, clear cache.
     */
    private function runPackageUpdate(PackageUpdater $updater, string $zipPath, UpdateChecker $checker, string $newVersion): RedirectResponse
    {
        @set_time_limit(0);

        $secret = bin2hex(random_bytes(8));
        Artisan::call('down', ['--secret' => $secret, '--render' => 'errors::503']);

        try {
            $updater->apply(
                $zipPath,
                base_path(),
                (array) config('cms.packager.protected', ['.env', 'storage', 'bootstrap/cache']),
                $checker->currentVersion(),
            );
        } catch (Throwable $e) {
            report($e);
            Artisan::call('up', ['--secret' => $secret]);

            return back()->with('error', __('updater.apply_failed', ['message' => $e->getMessage()]));
        }

        Artisan::call('up', ['--secret' => $secret]);

        return redirect()
            ->route('admin.dashboard')
            ->with('success', __('updater.applied', ['version' => 'v'.$newVersion]));
    }
}
