<?php

namespace Dotlib\BrunoDocs\Bruno;

use Dotlib\BrunoDocs\Support\BalancedDelimiter;

/**
 * Reads top-level Bruno blocks, including nested JSON braces.
 */
final class BruParser
{
    /**
     * Split a .bru file into named blocks.
     *
     * @return array<string, string>
     */
    public function parse(string $contents): array
    {
        $blocks = [];
        $length = strlen($contents);
        $cursor = 0;

        while ($cursor < $length) {
            $cursor = $this->skipWhitespace($contents, $cursor, $length);

            if ($cursor >= $length) {
                break;
            }

            $header = $this->readHeader($contents, $cursor);

            if ($header === null) {
                $cursor++;

                continue;
            }

            $body = $this->readBody($contents, $header['cursor'], $length, $header['opener']);
            $blocks[$header['name']] = trim($body['text']);
            $cursor = $body['cursor'];
        }

        return $blocks;
    }

    /**
     * Parse `key: value` lines from a block body.
     *
     * @return array<string, string>
     */
    public function parseDictionary(string $body): array
    {
        $dictionary = [];

        foreach ($this->lines($body) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = explode(':', $line, 2);
            $dictionary[trim($key)] = trim($value);
        }

        return $dictionary;
    }

    /**
     * Parse query rows. A leading ~ marks a disabled example.
     *
     * @return list<array{name: string, value: string, disabled: bool}>
     */
    public function parseQuery(string $body): array
    {
        $rows = [];

        foreach ($this->lines($body) as $line) {
            if (preg_match('/^(~)?([^:\s]+)\s*:\s*(.*)$/', $line, $matches) !== 1) {
                continue;
            }

            $rows[] = [
                'name' => $matches[2],
                'value' => trim($matches[3]),
                'disabled' => $matches[1] === '~',
            ];
        }

        return $rows;
    }

    /**
     * @return array{name: string, cursor: int, opener: string}|null
     */
    private function readHeader(string $contents, int $cursor): ?array
    {
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_:-]*)[ \t]*([{\[])/', substr($contents, $cursor), $matches) !== 1) {
            return null;
        }

        return [
            'name' => $matches[1],
            'cursor' => $cursor + strlen($matches[0]),
            'opener' => $matches[2],
        ];
    }

    /**
     * @return array{text: string, cursor: int}
     */
    private function readBody(string $contents, int $cursor, int $length, string $opener): array
    {
        $closer = $opener === '{' ? '}' : ']';
        $depth = 1;
        $start = $cursor;

        while ($cursor < $length && $depth > 0) {
            $character = $contents[$cursor];

            if ($character === '"') {
                $cursor = BalancedDelimiter::skipQuoted($contents, $cursor, $length);

                continue;
            }

            if ($character === $opener) {
                $depth++;
            } elseif ($character === $closer) {
                $depth--;
            }

            $cursor++;
        }

        $end = $depth === 0 ? $cursor - 1 : $cursor;

        return [
            'text' => substr($contents, $start, max(0, $end - $start)),
            'cursor' => $cursor,
        ];
    }

    private function skipWhitespace(string $contents, int $cursor, int $length): int
    {
        while ($cursor < $length && ctype_space($contents[$cursor])) {
            $cursor++;
        }

        return $cursor;
    }

    /**
     * @return list<string>
     */
    private function lines(string $body): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $body);

        if ($lines === false) {
            return [];
        }

        $trimmed = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line !== '') {
                $trimmed[] = $line;
            }
        }

        return $trimmed;
    }
}
