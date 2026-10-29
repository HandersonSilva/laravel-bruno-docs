<?php

namespace Dotlib\BrunoDocs\Bruno;

/**
 * A Bruno environment reduced to its public base URL.
 */
final class BrunoServer
{
    public function __construct(
        public readonly string $name,
        public readonly string $url,
    ) {}
}
