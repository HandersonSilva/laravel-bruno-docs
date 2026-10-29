<?php

namespace Dotlib\BrunoDocs;

/**
 * Reads bruno-docs config and keeps public/private resolution in one place.
 */
final class BrunoDocsSettings
{
    /**
     * Whether the documentation routes should respond.
     */
    public static function enabled(): bool
    {
        return filter_var(config('bruno-docs.enabled'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * URI prefix without leading or trailing slashes.
     */
    public static function path(): string
    {
        $path = trim((string) config('bruno-docs.path'), '/');

        return $path === '' ? 'docs' : $path;
    }

    /**
     * Resolved access mode. Anything other than "public" is private.
     */
    public static function access(): string
    {
        return config('bruno-docs.access') === 'public' ? 'public' : 'private';
    }

    /**
     * Middleware that runs after the web group for the current access mode.
     *
     * Private mode authenticates with the configured guard.
     *
     * @return list<string>
     */
    public static function routeMiddleware(): array
    {
        $extra = self::configuredMiddleware();

        if (self::access() !== 'private') {
            return $extra;
        }

        if ($extra === []) {
            return ['auth:'.self::guard(), 'verified'];
        }

        return self::applyGuard($extra);
    }

    /**
     * Authentication guard used by private docs. Blank values use "web".
     */
    public static function guard(): string
    {
        $guard = config('bruno-docs.guard');

        if (! is_string($guard) || trim($guard) === '') {
            return 'web';
        }

        return trim($guard);
    }

    /**
     * Absolute path of the Bruno collection, or an empty string when unset.
     */
    public static function collectionPath(): string
    {
        $path = config('bruno-docs.collection');

        return is_string($path) ? $path : '';
    }

    /**
     * Optional title override. Null keeps the name from bruno.json.
     */
    public static function title(): ?string
    {
        $title = config('bruno-docs.title');

        if (! is_string($title) || $title === '') {
            return null;
        }

        return $title;
    }

    /**
     * Whether the generated spec is cached until the collection files change.
     */
    public static function cacheEnabled(): bool
    {
        return filter_var(config('bruno-docs.cache'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Emails allowed to open private docs.
     *
     * An empty list means every authenticated user. Comparison is case-insensitive.
     *
     * @return list<string>
     */
    public static function allowedEmails(): array
    {
        $raw = config('bruno-docs.allowed_emails');

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $emails = [];

        foreach (explode('|', $raw) as $email) {
            $email = strtolower(trim($email));

            if ($email === '') {
                continue;
            }

            $emails[$email] = $email;
        }

        return array_values($emails);
    }

    /**
     * @return list<string>
     */
    private static function configuredMiddleware(): array
    {
        $configured = config('bruno-docs.middleware.'.self::access(), []);
        $extra = [];

        if (! is_array($configured)) {
            return [];
        }

        foreach ($configured as $middleware) {
            if (! is_string($middleware) || $middleware === '' || $middleware === 'web') {
                continue;
            }

            $extra[] = $middleware;
        }

        return $extra;
    }

    /**
     * @param  list<string>  $middleware
     * @return list<string>
     */
    private static function applyGuard(array $middleware): array
    {
        $auth = 'auth:'.self::guard();
        $applied = [];
        $hasAuth = false;

        foreach ($middleware as $item) {
            if ($item === 'auth' || str_starts_with($item, 'auth:')) {
                $applied[] = $auth;
                $hasAuth = true;

                continue;
            }

            $applied[] = $item;
        }

        if ($hasAuth) {
            return $applied;
        }

        array_unshift($applied, $auth);

        return $applied;
    }
}
