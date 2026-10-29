<?php

namespace Dotlib\BrunoDocs\OpenApi;

use Dotlib\BrunoDocs\Bruno\CollectionReader;
use Dotlib\BrunoDocs\Exceptions\BrunoCollectionNotFoundException;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the OpenAPI document and optionally caches it by collection fingerprint.
 */
final class OpenApiFactory
{
    public function __construct(
        private readonly CollectionReader $reader,
        private readonly OpenApiBuilder $builder,
    ) {}

    /**
     * Build the spec for a collection directory.
     *
     * @return array<string, mixed>
     *
     * @throws BrunoCollectionNotFoundException
     */
    public function make(string $path, ?string $title, bool $cache): array
    {
        if ($path === '' || ! is_dir($path)) {
            throw BrunoCollectionNotFoundException::at($path);
        }

        $build = fn (): array => $this->builder->build($this->reader->read($path), $title);

        if (! $cache) {
            return $build();
        }

        return Cache::rememberForever('bruno-docs.'.$this->reader->fingerprint($path), $build);
    }
}
