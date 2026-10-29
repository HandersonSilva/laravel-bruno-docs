<?php

namespace Dotlib\BrunoDocs\Bruno;

/**
 * A Bruno collection ready to be turned into OpenAPI.
 */
final class BrunoCollection
{
    /**
     * @param  list<BrunoServer>  $servers
     * @param  list<BrunoRequest>  $requests
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $defaultAuth,
        public readonly array $servers,
        public readonly array $requests,
    ) {}
}
