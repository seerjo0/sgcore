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
use Symfony\Component\HttpFoundation\StreamedResponse;
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

        if ($apply->repairPhpHandler() !== []) {
            session()->flash('status', __('updater.htaccess_repaired'));
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
     * The progress page: a small terminal that streams the download/copy/
     * migration steps while the site is in maintenance mode.
     */
    public function progress(
        Request $request,
        UpdateChecker $checker,
        UpdatePackageStore $store,
        PackageApply $apply,
    ): View|RedirectResponse {
        if ($apply->heal()) {
            session()->flash('status', __('updater.maintenance_recovered'));
        }

        $apply->repairPhpHandler();

        if (! $this->canUsePackages()) {
            return redirect()
                ->route('admin.configuracoes.atualizacoes.index')
                ->with('error', __('updater.package_disabled'));
        }

        $mode = $this->mode($request);
        $current = $checker->currentVersion();
        $stored = $store->current();

        if ($mode === 'install') {
            if ($stored === null) {
                return redirect()
                    ->route('admin.configuracoes.atualizacoes.index')
                    ->with('error', __('updater.package_none'));
            }

            if (! Version::isNewer((string) $stored['version'], $current)) {
                return redirect()
                    ->route('admin.configuracoes.atualizacoes.index')
                    ->with('error', __('updater.package_not_newer', ['version' => $stored['version']]));
            }

            $version = (string) $stored['version'];
            $bytes = (int) ($stored['bytes'] ?? 0);
        } else {
            $update = $checker->updateAvailable(mayFetch: false);

            if ($update === null) {
                return redirect()
                    ->route('admin.configuracoes.atualizacoes.index')
                    ->with('status', __('updater.up_to_date'));
            }

            $asset = $checker->packageAsset($update);

            if ($asset === null && ! ($stored !== null && Version::isNewer((string) $stored['version'], $current))) {
                return redirect()
                    ->route('admin.configuracoes.atualizacoes.index')
                    ->with('error', __('updater.package_no_asset'));
            }

            $version = (string) $update['version'];
            $bytes = (int) ($asset['size'] ?? ($stored['bytes'] ?? 0));
        }

        return view('core::admin.updates.progress', [
            'mode' => $mode,
            'from' => $current,
            'version' => $version,
            'bytes' => $bytes,
            'streamUrl' => route('admin.configuracoes.atualizacoes.executar.iniciar'),
            'backUrl' => route('admin.configuracoes.atualizacoes.index'),
            'maintenance' => $apply->status(),
        ]);
    }

    /**
     * Stream the whole update as newline-delimited JSON so the progress page can
     * render it live. Runs in the request's PHP process (shared hosting has no
     * queue/exec), keeps going if the browser disconnects and always leaves
     * maintenance mode through PackageApply.
     */
    public function run(
        Request $request,
        UpdateChecker $checker,
        PackageSource $source,
        UpdatePackageStore $store,
        PackageApply $apply,
    ): StreamedResponse {
        $mode = $this->mode($request);
        $user = $request->user();
        $clientIp = (string) $request->ip();

        return response()->stream(function () use ($mode, $user, $clientIp, $checker, $source, $store, $apply): void {
            $this->streamSetup();

            $emit = function (array $event): void {
                echo json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";

                if (ob_get_level() > 0) {
                    @ob_flush();
                }

                @flush();
            };

            $key = 'updater.run:'.($user?->id ?? $clientIp);

            try {
                if (! $this->canUsePackages()) {
                    throw new RuntimeException(__('updater.package_disabled'));
                }

                if (RateLimiter::tooManyAttempts($key, 2)) {
                    throw new RuntimeException(__('updater.throttled', ['seconds' => RateLimiter::availableIn($key)]));
                }

                RateLimiter::hit($key, 300);

                $current = $checker->currentVersion();
                $stored = $store->current();
                $canUseStored = $stored !== null && Version::isNewer((string) $stored['version'], $current);

                if ($mode === 'install' && ! $canUseStored) {
                    throw new RuntimeException($stored === null
                        ? __('updater.package_none')
                        : __('updater.package_not_newer', ['version' => $stored['version']]));
                }

                if ($canUseStored && ($mode === 'install' || ! (bool) config('cms.updater.apply.download', true))) {
                    $path = (string) $stored['path'];
                    $version = (string) $stored['version'];

                    $emit([
                        'type' => 'info',
                        'message' => __('updater.progress_stored', ['version' => $version]),
                        'percent' => 4,
                    ]);
                } else {
                    $update = $checker->updateAvailable(mayFetch: true, force: true);

                    if ($update === null) {
                        throw new RuntimeException(__('updater.up_to_date'));
                    }

                    $asset = $checker->packageAsset($update);

                    if ($asset === null) {
                        throw new RuntimeException(__('updater.package_no_asset'));
                    }

                    $emit([
                        'type' => 'info',
                        'message' => __('updater.progress_download_start', ['version' => $update['tag']]),
                        'percent' => 2,
                    ]);

                    $downloaded = $source->fetch($asset, function (array $event) use ($emit): void {
                        $emit(['type' => 'info'] + $event);
                    });

                    try {
                        $saved = $store->store($downloaded['path'], (string) $update['version'], (string) ($asset['name'] ?? ''));
                    } finally {
                        @unlink($downloaded['path']);
                    }

                    $path = (string) $saved['path'];
                    $version = (string) $update['version'];
                }

                $result = $apply->apply($path, $current, function (array $event) use ($emit): void {
                    $type = in_array($event['stage'] ?? '', ['maintenance', 'htaccess'], true) ? 'warn' : 'info';

                    $emit(['type' => $type] + $event);
                });

                $store->discard();

                $emit([
                    'type' => 'ok',
                    'message' => __('updater.progress_done', ['version' => 'v'.($result['to'] ?? $version)]),
                    'percent' => 100,
                    'done' => true,
                    'ok' => true,
                    'redirect' => route('admin.dashboard'),
                ]);
            } catch (Throwable $e) {
                report($e);

                $emit([
                    'type' => 'error',
                    'message' => __('updater.apply_failed', ['message' => $e->getMessage()]),
                    'percent' => 100,
                    'done' => true,
                    'ok' => false,
                    'redirect' => route('admin.configuracoes.atualizacoes.index'),
                ]);
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'X-Accel-Buffering' => 'no',
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

    private function mode(Request $request): string
    {
        return $request->input('modo', $request->query('modo')) === 'atualizar' ? 'update' : 'install';
    }

    /**
     * Shared hosting usually buffers output; a progress stream is useless if the
     * proxy holds the whole body until the request ends.
     */
    private function streamSetup(): void
    {
        ignore_user_abort(true);

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        @ini_set('zlib.output_compression', '0');

        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
    }
}
