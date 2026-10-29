<?php

namespace Dotlib\BrunoDocs\Support;

/**
 * Brace matching that ignores characters inside JSON strings.
 */
final class BalancedDelimiter
{
    /**
     * Advance past a double-quoted string that starts at $quotePosition.
     */
    public static function skipQuoted(string $contents, int $quotePosition, int $length): int
    {
        $cursor = $quotePosition + 1;

        while ($cursor < $length) {
            if ($contents[$cursor] === '\\') {
                $cursor += 2;

                continue;
            }

            if ($contents[$cursor] === '"') {
                return $cursor + 1;
            }

            $cursor++;
        }

        return $length;
    }

    /**
     * Return the balanced {...} or [...] slice that starts at $start.
     */
    public static function slice(string $text, int $start): ?string
    {
        if (! isset($text[$start])) {
            return null;
        }

        $opener = $text[$start];

        if ($opener !== '{' && $opener !== '[') {
            return null;
        }

        $closer = $opener === '{' ? '}' : ']';
        $depth = 0;
        $length = strlen($text);

        for ($cursor = $start; $cursor < $length; $cursor++) {
            $character = $text[$cursor];

            if ($character === '"') {
                $cursor = self::skipQuoted($text, $cursor, $length) - 1;

                continue;
            }

            if ($character === $opener) {
                $depth++;
            } elseif ($character === $closer) {
                $depth--;

                if ($depth === 0) {
                    return substr($text, $start, $cursor - $start + 1);
                }
            }
        }

        return null;
    }
}
