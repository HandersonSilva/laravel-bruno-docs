<?php

namespace Dotlib\BrunoDocs\Http\Middleware;

use Closure;
use Dotlib\BrunoDocs\BrunoDocsSettings;
use Illuminate\Http\Request;
use Illuminate\Routing\Pipeline;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides the docs when disabled and applies the configured public or private middleware.
 */
final class EnsureBrunoDocsAccess
{
    /**
     * Allow the request, require the private middleware stack, or answer 404.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! BrunoDocsSettings::enabled()) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $middleware = BrunoDocsSettings::routeMiddleware();

        if ($middleware === []) {
            return $next($request);
        }

        $resolved = app('router')->resolveMiddleware($middleware);

        return (new Pipeline(app()))
            ->send($request)
            ->through($resolved)
            ->then(function (Request $request) use ($next): Response {
                $this->ensureAllowedEmail($request);

                return $next($request);
            });
    }

    /**
     * Reject a private session whose email is outside the allow list.
     */
    private function ensureAllowedEmail(Request $request): void
    {
        if (BrunoDocsSettings::access() !== 'private') {
            return;
        }

        $allowed = BrunoDocsSettings::allowedEmails();

        if ($allowed === []) {
            return;
        }

        if (in_array($this->userEmail($request), $allowed, true)) {
            return;
        }

        abort(Response::HTTP_FORBIDDEN, __('You are not allowed to view the API documentation.'));
    }

    private function userEmail(Request $request): string
    {
        $user = $request->user();

        if ($user === null) {
            return '';
        }

        return strtolower(trim((string) ($user->email ?? '')));
    }
}
