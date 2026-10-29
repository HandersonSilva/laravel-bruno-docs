<?php

namespace Dotlib\BrunoDocs\OpenApi;

use Dotlib\BrunoDocs\Support\BalancedDelimiter;

/**
 * Pulls HTTP status examples out of a Bruno docs block.
 */
final class DocsResponseExtractor
{
    /**
     * @return array<string, array{description: string, example: mixed}>
     */
    public function extract(string $docs): array
    {
        $pattern = '/^[ \t]*(?:-[ \t]*)?(?:Resposta[ \t]+)?(\d{3})\b(.*)$/m';

        if (preg_match_all($pattern, $docs, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return [];
        }

        $responses = [];
        $count = count($matches[0]);

        for ($index = 0; $index < $count; $index++) {
            $code = $matches[1][$index][0];
            $rest = $matches[2][$index][0];
            $matchEnd = $matches[0][$index][1] + strlen($matches[0][$index][0]);
            $nextStart = $index + 1 < $count ? $matches[0][$index + 1][1] : strlen($docs);
            $segment = $rest.substr($docs, $matchEnd, $nextStart - $matchEnd);
            [$example, $prose] = $this->splitExample($segment);
            $this->merge($responses, $code, $prose, $example);
        }

        return $responses;
    }

    /**
     * @param  array<string, array{description: string, example: mixed}>  $responses
     */
    private function merge(array &$responses, string $code, string $prose, mixed $example): void
    {
        if (! isset($responses[$code])) {
            $responses[$code] = [
                'description' => $prose,
                'example' => $example,
            ];

            return;
        }

        if ($prose !== '') {
            $existing = $responses[$code]['description'];
            $responses[$code]['description'] = $existing === '' ? $prose : $existing."\n".$prose;
        }

        if ($responses[$code]['example'] === null && $example !== null) {
            $responses[$code]['example'] = $example;
        }
    }

    /**
     * @return array{0: mixed, 1: string}
     */
    private function splitExample(string $segment): array
    {
        $start = null;
        $length = strlen($segment);

        for ($cursor = 0; $cursor < $length; $cursor++) {
            if ($segment[$cursor] === '{' || $segment[$cursor] === '[') {
                $start = $cursor;

                break;
            }
        }

        if ($start === null) {
            return [null, $this->cleanProse($segment)];
        }

        $json = BalancedDelimiter::slice($segment, $start);

        if ($json === null) {
            return [null, $this->cleanProse($segment)];
        }

        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [null, $this->cleanProse($segment)];
        }

        $before = substr($segment, 0, $start);
        $after = substr($segment, $start + strlen($json));

        return [$decoded, $this->cleanProse($before.' '.$after)];
    }

    private function cleanProse(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/^[:\-]+\s*/', '', $text) ?? $text;

        return trim($text);
    }
}
