<?php

namespace Dotlib\BrunoDocs\Exceptions;

use RuntimeException;

/**
 * The configured Bruno collection directory does not exist.
 */
final class BrunoCollectionNotFoundException extends RuntimeException
{
    /**
     * Build the exception for a missing collection path.
     */
    public static function at(string $path): self
    {
        $location = $path === '' ? '(empty)' : $path;

        return new self("Bruno collection directory [{$location}] was not found.");
    }
}
