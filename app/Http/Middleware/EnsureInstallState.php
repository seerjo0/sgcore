<?php

namespace App\Http\Middleware;

use App\Modules\Core\Services\InstallerLock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstallState
{
    public function __construct(private InstallerLock $lock) {}

    /**
     * Force the installer flow while unlocked and block it once installed.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isInstallerRoute = $request->is('instalar', 'instalar/*');

        if (! $this->lock->exists()) {
            if (empty(config('app.key'))) {
                config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
            }

            if (! $isInstallerRoute && ! $request->is('up')) {
                return redirect('/instalar');
            }

            return $next($request);
        }

        if ($isInstallerRoute && ! $request->is('instalar/concluido')) {
            return redirect('/');
        }

        return $next($request);
    }
}
