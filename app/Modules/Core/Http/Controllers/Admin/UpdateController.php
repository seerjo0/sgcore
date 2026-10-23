<?php

namespace App\Modules\Core\Http\Controllers\Admin;

use App\Modules\Core\Services\PackageApply;
use App\Modules\Core\Services\PackageSource;
use App\Modules\Core\Services\PackageUpdater;
use App\Modules\Core\Services\Settings;
use App\Modules\Core\Services\UpdateChecker;
use App\Modules\Core\Services\UpdatePackageStore;
use App\Modules\Core\Services\UpdatePolicy;
use App\Modules\Core\Services\UpdateRunner;
use App\Modules\Core\Support\Version;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class UpdateController extends Controller
{
    /**
     * The dedicated "Updates" admin page: current vs latest version, the
     * download/install actions for package-based installs, the automatic
     * update policy and the manual apply actions.
     */
    public function index(
        UpdateChecker $checker,
        Settings $settings,
        UpdatePackageStore $store,
        UpdatePolicy $policy,
        PackageApply $apply,
    ): View {
        if ($apply->heal()) {
            session()->flash('status', __('updater.maintenance_recovered'));
        }

        $update = $checker->updateAvailable(mayFetch: false);
        $current = $checker->currentVersion();
        $stored = $store->current();

        return view('core::admin.updates.index', [
            'current' => $current,
            'update' => $update,
            'releaseUrl' => is_array($update) ? ($update['url'] ?? null) : null,
            'packageAsset' => $checker->packageAsset($update),
            'stored' => $stored,
            'storedNewer' => $stored !== null && Version::isNewer((string) $stored['version'], $current),
            'updaterEnabled' => (bool) config('cms.updater.enabled'),
            'applyEnabled' => (bool) config('cms.updater.apply.enabled'),
            'packageEnabled' => (bool) config('cms.updater.apply.package', true),
            'downloadEnabled' => (bool) config('cms.updater.apply.download', true),
            'autoCheck' => $policy->autoCheck(),
            'autoDownload' => $policy->autoDownload(),
            'autoInstall' => $policy->autoInstall(),
            'autoLast' => $settings->get('auto_update_last'),
            'maintenance' => $apply->status(),
            'repo' => (string) config('cms.updater.repository', 'seerjo0/sgcore'),
        ]);
    }

    /**
     * Force-refresh the cached release info and report the outcome. Does not
     * apply anything — this is a read-only check.
     */
    public function check(Request $request, UpdateChecker $checker): RedirectResponse
    {
        if (! config('cms.updater.enabled')) {
            return back()->with('status', __('updater.disabled'));
        }

        if ($limited = $this->limit($request, 'check', 5, 1)) {
            return $limited;
        }

        // Explicit user action: always bypass the cache, otherwise a release
        // published after the last fetch stays invisible until the TTL ends.
        $update = $checker->updateAvailable(mayFetch: true, force: true);

        if ($update !== null) {
            return back()->with('status', __('updater.available', ['version' => $update['tag']]));
        }

        if (($error = $checker->lastError()) !== null) {
            return back()->with('error', __('updater.check_failed', ['message' => $error]));
        }

        return back()->with('status', __('updater.up_to_date'));
    }

    /**
     * Turn off a maintenance mode left behind by an interrupted update. This
     * route is the one path allowed through while the site is down.
     */
    public function releaseMaintenance(PackageApply $apply): RedirectResponse
    {
        $wasDown = $apply->release();

        return back()->with(
            $wasDown ? 'success' : 'status',
            __($wasDown ? 'updater.maintenance_released' : 'updater.maintenance_none'),
        );
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
     * Save the automatic update policy (check / download / install).
     */
    public function settings(Request $request, UpdatePolicy $policy): RedirectResponse
    {
        if (! config('cms.updater.enabled')) {
            return back()->with('error', __('updater.disabled'));
        }

        $data = $request->validate([
            'auto_check' => ['nullable', 'boolean'],
            'auto_download' => ['nullable', 'boolean'],
            'auto_install' => ['nullable', 'boolean'],
        ]);

        $policy->save([
            'auto_check' => (bool) ($data['auto_check'] ?? false),
            'auto_download' => (bool) ($data['auto_download'] ?? false),
            'auto_install' => (bool) ($data['auto_install'] ?? false),
        ]);

        return back()->with('status', __('updater.settings_saved'));
    }

    /**
     * Download the release's package asset onto the shelf, without applying it.
     */
    public function download(
        Request $request,
        UpdateChecker $checker,
        PackageSource $source,
        UpdatePackageStore $store,
    ): RedirectResponse {
        if (! $this->canUsePackages()) {
            return back()->with('error', __('updater.package_disabled'));
        }

        if ($limited = $this->limit($request, 'download', 3, 5)) {
            return $limited;
        }

        $update = $checker->updateAvailable(mayFetch: true);

        if ($update === null) {
            return back()->with('status', __('updater.up_to_date'));
        }

        $asset = $checker->packageAsset($update);

        if ($asset === null) {
            return back()->with('error', __('updater.package_no_asset'));
        }

        try {
            $downloaded = $source->fetch($asset);
        } catch (RuntimeException $e) {
            return back()->with('error', __('updater.download_failed', ['message' => $e->getMessage()]));
        }

        try {
            $stored = $store->store($downloaded['path'], $update['version'], (string) ($asset['name'] ?? ''));
        } catch (RuntimeException $e) {
            return back()->with('error', __('updater.download_failed', ['message' => $e->getMessage()]));
        } finally {
            @unlink($downloaded['path']);
        }

        return back()->with('success', __('updater.package_saved', [
            'version' => $update['tag'],
            'size' => number_format($stored['bytes'] / 1048576, 1, ',', '.').' MB',
        ]));
    }

    /**
     * Install the package previously downloaded onto the shelf.
     */
    public function install(
        Request $request,
        UpdateChecker $checker,
        UpdatePackageStore $store,
        PackageApply $apply,
    ): RedirectResponse {
        $request->validate(['confirm' => ['accepted']]);

        if ($limited = $this->limit($request, 'install', 2, 5)) {
            return $limited;
        }

        if (! $this->canUsePackages()) {
            return back()->with('error', __('updater.package_disabled'));
        }

        $stored = $store->current();

        if ($stored === null) {
            return back()->with('error', __('updater.package_none'));
        }

        if (! Version::isNewer((string) $stored['version'], $checker->currentVersion())) {
            return back()->with('error', __('updater.package_not_newer', ['version' => $stored['version']]));
        }

        $path = (string) $stored['path'];

        @set_time_limit(0);

        try {
            $apply->apply($path, $checker->currentVersion());
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('updater.apply_failed', ['message' => $e->getMessage()]));
        }

        $store->discard();

        return redirect()
            ->route('admin.dashboard')
            ->with('success', __('updater.applied', ['version' => 'v'.$stored['version']]));
    }

    /**
     * Delete the downloaded package from the shelf.
     */
    public function discardPackage(Request $request, UpdatePackageStore $store): RedirectResponse
    {
        if ($limited = $this->limit($request, 'discard', 5, 1)) {
            return $limited;
        }

        $removed = $store->discard();

        return back()->with(
            $removed ? 'status' : 'error',
            $removed ? __('updater.package_discarded') : __('updater.package_none'),
        );
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

        if ($limited = $this->limit($request, 'apply', 2, 5)) {
            return $limited;
        }

        $update = $checker->updateAvailable(mayFetch: true);

        if ($update === null) {
            return back()->with('status', __('updater.up_to_date'));
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

        if (! $this->canUsePackages()) {
            return back()->with('error', __('updater.package_disabled'));
        }

        if ($limited = $this->limit($request, 'upload', 2, 5)) {
            return $limited;
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

        return $this->runPackageUpdate($file->getRealPath(), $checker, $info['version']);
    }

    /**
     * Shared package apply flow: maintenance mode, in-place update, clear cache.
     */
    private function runPackageUpdate(string $zipPath, UpdateChecker $checker, string $newVersion): RedirectResponse
    {
        @set_time_limit(0);

        try {
            app(PackageApply::class)->apply($zipPath, $checker->currentVersion());
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('updater.apply_failed', ['message' => $e->getMessage()]));
        }

        return redirect()
            ->route('admin.dashboard')
            ->with('success', __('updater.applied', ['version' => 'v'.$newVersion]));
    }

    /**
     * Centralized throttle, so the limits live in one place and the 429 is
     * returned with a useful message instead of Laravel's default page.
     */
    private function limit(Request $request, string $bucket, int $max, int $minutes): ?RedirectResponse
    {
        $key = "updater.{$bucket}:".($request->user()?->id ?? $request->ip());

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->with('error', __('updater.throttled', ['seconds' => $seconds]));
        }

        RateLimiter::hit($key, $minutes * 60);

        return null;
    }

    private function canUsePackages(): bool
    {
        return (bool) config('cms.updater.apply.enabled')
            && (bool) config('cms.updater.apply.package', true);
    }
}
