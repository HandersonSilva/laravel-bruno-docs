<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Documentation route
    |--------------------------------------------------------------------------
    |
    | enabled: when false the routes are not registered.
    | path: URI prefix, outside /api so API token middleware does not apply.
    | access: "public" or "private". Any other value is treated as private.
    | guard: auth guard for private mode. Blank uses "web".
    |
    */

    'enabled' => env('BRUNO_DOCS_ENABLED', true),

    'path' => env('BRUNO_DOCS_PATH', 'docs'),

    'access' => env('BRUNO_DOCS_ACCESS', 'private'),

    'guard' => env('BRUNO_DOCS_GUARD', 'web'),

    'middleware' => [
        'public' => ['web'],
        'private' => ['web', 'auth', 'verified'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Bruno collection
    |--------------------------------------------------------------------------
    |
    | Absolute path to the collection directory (the folder that contains
    | bruno.json). Null until the application publishes this config.
    |
    */

    'collection' => env('BRUNO_DOCS_COLLECTION'),

    'title' => env('BRUNO_DOCS_TITLE'),

    'cache' => env('BRUNO_DOCS_CACHE', true),

    /*
    |--------------------------------------------------------------------------
    | Private allow list
    |--------------------------------------------------------------------------
    |
    | Pipe-separated emails. Empty allows every authenticated user. When set,
    | private docs require a session whose email is in this list. Ignored
    | when access is public.
    |
    */

    'allowed_emails' => env('BRUNO_DOCS_ALLOWED_EMAILS', ''),

];
