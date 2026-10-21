<?php

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Renders the application's own branded error page instead of Laravel's stock
 * screens. In debug mode it surfaces the same technical details Laravel shows
 * (exception class, message, file:line, a source snippet around the faulting
 * line and a readable stack) so a maintainer can still diagnose from the page.
 */
class ErrorPresenter
{
    /**
     * Return a custom HTML response for browser requests, or null so the
     * framework renders its default (JSON for API/`expectsJson`, or the
     * standard maintenance handling) untouched.
     */
    public static function render(Request $request, Throwable $e): ?Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }

        // Keep the framework's redirect semantics for auth and validation.
        if ($e instanceof AuthenticationException || $e instanceof ValidationException) {
            return null;
        }

        $status = self::status($e);
        $debug = (bool) config('app.debug');

        return response()->view('errors.error', [
            'status' => $status,
            'title' => self::title($status),
            'message' => self::message($status),
            'debug' => $debug,
            'exception' => $debug ? self::exceptionData($e) : null,
            'request' => $debug ? self::requestData($request) : null,
        ], $status);
    }

    public static function status(Throwable $e): int
    {
        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode();
        }

        return match (true) {
            $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => 404,
            $e instanceof AuthenticationException => 401,
            $e instanceof AuthorizationException, $e instanceof AccessDeniedHttpException => 403,
            $e instanceof TooManyRequestsHttpException => 429,
            $e instanceof MethodNotAllowedHttpException => 405,
            $e instanceof TokenMismatchException => 419,
            default => 500,
        };
    }

    /**
     * A short, human title per status (already localized).
     */
    public static function title(int $status): string
    {
        $map = [
            400 => 'errors.t_400',
            401 => 'errors.t_401',
            403 => 'errors.t_403',
            404 => 'errors.t_404',
            405 => 'errors.t_405',
            413 => 'errors.t_413',
            419 => 'errors.t_419',
            429 => 'errors.t_429',
            500 => 'errors.t_500',
            503 => 'errors.t_503',
            504 => 'errors.t_504',
        ];

        $key = $map[$status] ?? ($status >= 500 ? 'errors.t_500' : 'errors.t_generic');

        return __($key);
    }

    public static function message(int $status): string
    {
        $map = [
            400 => 'errors.m_400',
            401 => 'errors.m_401',
            403 => 'errors.m_403',
            404 => 'errors.m_404',
            405 => 'errors.m_405',
            413 => 'errors.m_413',
            419 => 'errors.m_419',
            429 => 'errors.m_429',
            500 => 'errors.m_500',
            503 => 'errors.m_503',
            504 => 'errors.m_504',
        ];

        $key = $map[$status] ?? ($status >= 500 ? 'errors.m_500' : 'errors.m_generic');

        return __($key);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function exceptionData(Throwable $e): array
    {
        return [
            'class' => $e::class,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'snippet' => self::snippet($e->getFile(), $e->getLine()),
            'trace' => self::trace($e),
            'previous' => $e->getPrevious() ? [
                'class' => $e->getPrevious()::class,
                'message' => $e->getPrevious()->getMessage(),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function requestData(Request $request): array
    {
        return [
            'method' => $request->getMethod(),
            'uri' => '/'.ltrim($request->path(), '/'),
            'full' => $request->fullUrlWithQuery([]),
            'ip' => $request->ip(),
            'time' => now()->toIso8601String(),
            'user' => auth()->check() ? (string) auth()->id() : 'guest',
            'headers' => $request->headers->all(),
        ];
    }

    /**
     * Readable lines of source around the faulting line, with the current one
     * flagged. Returns null when the file is unavailable.
     *
     * @return array{start:int, line:int, rows:list<array{n:int, code:string, is_at:bool, highlight:bool}>}|null
     */
    protected static function snippet(string $file, int $line, int $context = 8): ?array
    {
        if ($line <= 0 || ! is_readable($file)) {
            return null;
        }

        $lines = @file($file);

        if ($lines === false || $lines === []) {
            return null;
        }

        $start = max(1, $line - $context);
        $end = min(count($lines), $line + $context);
        $rows = [];

        for ($n = $start; $n <= $end; $n++) {
            $code = rtrim((string) $lines[$n - 1], "\r\n");

            $rows[] = [
                'n' => $n,
                'code' => $code,
                'is_at' => $n === $line,
                'highlight' => abs($n - $line) <= 1,
            ];
        }

        return ['start' => $start, 'line' => $line, 'rows' => $rows];
    }

    /**
     * A compact, human-readable call stack (file:line + the class::method if any).
     *
     * @return list<array{file:string, line:int, call:string}>
     */
    protected static function trace(Throwable $e): array
    {
        $out = [];

        foreach (array_slice($e->getTrace(), 0, 20) as $frame) {
            $file = (string) ($frame['file'] ?? '[internal]');
            $line = (int) ($frame['line'] ?? 0);

            $call = '';
            if (isset($frame['class'])) {
                $short = class_basename($frame['class']);
                $call = $short.$frame['type'].($frame['function'] ?? '');
            } elseif (isset($frame['function'])) {
                $call = $frame['function'].'()';
            }

            $out[] = ['file' => $file, 'line' => $line, 'call' => $call];
        }

        return $out;
    }
}
