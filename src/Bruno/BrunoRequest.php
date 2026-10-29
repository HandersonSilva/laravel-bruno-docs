<?php

namespace Dotlib\BrunoDocs\Bruno;

/**
 * One HTTP request inside a Bruno collection.
 */
final class BrunoRequest
{
    /**
     * @param  list<array{name: string, value: string, disabled: bool}>  $query
     */
    public function __construct(
        public readonly string $name,
        public readonly int $sequence,
        public readonly string $method,
        public readonly string $url,
        public readonly ?string $auth,
        public readonly string $docs,
        public readonly ?string $body,
        public readonly array $query,
        public readonly string $tag,
        public readonly int $tagSequence,
        public readonly int $nestedSequence,
    ) {}
}
