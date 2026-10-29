<?php

namespace Dotlib\BrunoDocs\Http\Controllers;

use Dotlib\BrunoDocs\Bruno\CollectionReader;
use Dotlib\BrunoDocs\BrunoDocsSettings;
use Dotlib\BrunoDocs\Exceptions\BrunoCollectionNotFoundException;
use Dotlib\BrunoDocs\OpenApi\OpenApiFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * Swagger UI page and OpenAPI document.
 */
final class DocsController
{
    /**
     * Render the Swagger UI page.
     *
     * @throws BrunoCollectionNotFoundException
     */
    public function show(CollectionReader $reader): View
    {
        $path = $this->collectionPath();

        return view('bruno-docs::docs', [
            'title' => BrunoDocsSettings::title() ?? $reader->collectionName($path),
            'specUrl' => route('bruno-docs.spec'),
            'cssUrl' => asset('vendor/bruno-docs/swagger-ui.css'),
            'jsUrl' => asset('vendor/bruno-docs/swagger-ui-bundle.js'),
        ]);
    }

    /**
     * Return the OpenAPI document generated from the Bruno collection.
     *
     * @throws BrunoCollectionNotFoundException
     */
    public function spec(OpenApiFactory $factory): JsonResponse
    {
        $spec = $factory->make(
            $this->collectionPath(),
            BrunoDocsSettings::title(),
            BrunoDocsSettings::cacheEnabled(),
        );

        return response()->json($spec, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @throws BrunoCollectionNotFoundException
     */
    private function collectionPath(): string
    {
        $path = BrunoDocsSettings::collectionPath();

        if ($path === '' || ! is_dir($path)) {
            throw BrunoCollectionNotFoundException::at($path);
        }

        return $path;
    }
}
