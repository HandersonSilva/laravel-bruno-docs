<?php

namespace Dotlib\BrunoDocs\OpenApi;

use Dotlib\BrunoDocs\Bruno\BrunoCollection;
use Dotlib\BrunoDocs\Bruno\BrunoRequest;
use Dotlib\BrunoDocs\Bruno\BrunoServer;

/**
 * Builds an OpenAPI 3 document from a Bruno collection.
 */
final class OpenApiBuilder
{
    private const SERVER_ORDER = [
        'local' => 0,
        'development' => 1,
        'homolog' => 2,
        'production' => 3,
    ];

    private const UUID = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/';

    public function __construct(private readonly DocsResponseExtractor $responses) {}

    /**
     * @return array<string, mixed>
     */
    public function build(BrunoCollection $collection, ?string $title = null): array
    {
        $paths = [];
        $tags = [];
        $operationIds = [];
        $usesBearer = false;

        foreach ($this->sorted($collection->requests) as $request) {
            if (! isset($tags[$request->tag])) {
                $tags[$request->tag] = ['name' => $request->tag];
            }

            $normalized = $this->normalizePath($request->url);
            $operation = $this->operation($request, $normalized['parameters'], $operationIds);

            if ($request->auth === 'bearer') {
                $operation['security'] = [['bearerAuth' => []]];
                $usesBearer = true;
            }

            $paths[$normalized['path']][strtolower($request->method)] = $operation;
        }

        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => ($title !== null && $title !== '') ? $title : $collection->name,
                'version' => '1.0.0',
            ],
            'paths' => $paths === [] ? new \stdClass : $paths,
        ];

        $servers = $this->servers($collection->servers);

        if ($servers !== []) {
            $spec['servers'] = $servers;
        }

        if ($tags !== []) {
            $spec['tags'] = array_values($tags);
        }

        if ($usesBearer) {
            $spec['components'] = [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                    ],
                ],
            ];
        }

        return $spec;
    }

    /**
     * @param  list<BrunoRequest>  $requests
     * @return list<BrunoRequest>
     */
    private function sorted(array $requests): array
    {
        usort($requests, function (BrunoRequest $left, BrunoRequest $right): int {
            return [
                $left->tagSequence,
                $left->nestedSequence,
                $left->sequence,
                $left->name,
            ] <=> [
                $right->tagSequence,
                $right->nestedSequence,
                $right->sequence,
                $right->name,
            ];
        });

        return $requests;
    }

    /**
     * @param  list<array<string, mixed>>  $pathParameters
     * @param  array<string, true>  $operationIds
     * @return array<string, mixed>
     */
    private function operation(BrunoRequest $request, array $pathParameters, array &$operationIds): array
    {
        $operation = [
            'tags' => [$request->tag],
            'summary' => $request->name,
            'operationId' => $this->operationId($request, $operationIds),
        ];

        if ($request->docs !== '') {
            $operation['description'] = $request->docs;
        }

        $parameters = array_merge($pathParameters, $this->queryParameters($request->query));

        if ($parameters !== []) {
            $operation['parameters'] = $parameters;
        }

        $body = $this->requestBody($request->body);

        if ($body !== null) {
            $operation['requestBody'] = $body;
        }

        $operation['responses'] = $this->responseMap($request->docs);

        return $operation;
    }

    /**
     * @param  array<string, true>  $operationIds
     */
    private function operationId(BrunoRequest $request, array &$operationIds): string
    {
        $raw = strtolower($request->method.' '.$request->tag.' '.$request->name);
        $base = trim(preg_replace('/[^a-z0-9]+/', '_', $raw) ?? $raw, '_');

        if ($base === '') {
            $base = 'operation';
        }

        $operationId = $base;
        $suffix = 2;

        while (isset($operationIds[$operationId])) {
            $operationId = $base.'_'.$suffix;
            $suffix++;
        }

        $operationIds[$operationId] = true;

        return $operationId;
    }

    /**
     * @return array{path: string, parameters: list<array<string, mixed>>}
     */
    private function normalizePath(string $url): array
    {
        $path = $this->pathFromUrl($url);
        $segments = explode('/', $path);
        $parameters = [];

        foreach ($segments as $index => $segment) {
            if (preg_match(self::UUID, $segment) !== 1) {
                continue;
            }

            $name = $this->parameterName($segments[$index - 1] ?? 'id', $parameters);
            $parameters[] = [
                'name' => $name,
                'in' => 'path',
                'required' => true,
                'schema' => [
                    'type' => 'string',
                    'format' => 'uuid',
                ],
                'example' => $segment,
            ];
            $segments[$index] = '{'.$name.'}';
        }

        $normalized = implode('/', $segments);

        if ($normalized === '' || $normalized[0] !== '/') {
            $normalized = '/'.$normalized;
        }

        return [
            'path' => $normalized,
            'parameters' => $parameters,
        ];
    }

    private function pathFromUrl(string $url): string
    {
        $withoutVariables = preg_replace('/\{\{[^}]+\}\}/', '', $url) ?? $url;
        $withoutQuery = explode('?', $withoutVariables, 2)[0];
        $path = trim($withoutQuery);

        if ($path === '') {
            return '/';
        }

        if (str_starts_with($path, '/')) {
            return $path;
        }

        $parsed = parse_url($path, PHP_URL_PATH);

        if (is_string($parsed) && $parsed !== '') {
            return $parsed;
        }

        return '/'.$path;
    }

    /**
     * @param  list<array<string, mixed>>  $parameters
     */
    private function parameterName(string $previous, array $parameters): string
    {
        if ($previous === '' || str_starts_with($previous, '{')) {
            $base = 'id';
        } elseif (str_ends_with($previous, 's') && ! str_ends_with($previous, 'ss')) {
            $base = substr($previous, 0, -1);
        } else {
            $base = $previous.'-id';
        }

        $name = $base;
        $suffix = 2;
        $existing = array_column($parameters, 'name');

        while (in_array($name, $existing, true)) {
            $name = $base.'-'.$suffix;
            $suffix++;
        }

        return $name;
    }

    /**
     * @param  list<array{name: string, value: string, disabled: bool}>  $rows
     * @return list<array<string, mixed>>
     */
    private function queryParameters(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $grouped[$row['name']][] = $row;
        }

        $parameters = [];

        foreach ($grouped as $name => $entries) {
            $parameters[] = $this->queryParameter((string) $name, $entries);
        }

        return $parameters;
    }

    /**
     * @param  list<array{name: string, value: string, disabled: bool}>  $entries
     * @return array<string, mixed>
     */
    private function queryParameter(string $name, array $entries): array
    {
        $enabled = [];
        $all = [];

        foreach ($entries as $entry) {
            $all[] = $entry['value'];

            if (! $entry['disabled']) {
                $enabled[] = $entry['value'];
            }
        }

        $source = $enabled !== [] ? $enabled : $all;
        $unique = $this->unique($all);
        $isArray = str_ends_with($name, '[]');
        $type = $this->scalarType($unique);

        if ($isArray) {
            return [
                'name' => $name,
                'in' => 'query',
                'schema' => [
                    'type' => 'array',
                    'items' => [
                        'type' => $type,
                    ],
                ],
                'style' => 'form',
                'explode' => true,
                'example' => $this->castList($source, $type),
            ];
        }

        $schema = ['type' => $type];

        if (count($unique) > 1) {
            $schema['enum'] = $this->castList($unique, $type);
        }

        return [
            'name' => $name,
            'in' => 'query',
            'schema' => $schema,
            'example' => $this->castValue($source[0], $type),
        ];
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function unique(array $values): array
    {
        $unique = [];

        foreach ($values as $value) {
            if (! in_array($value, $unique, true)) {
                $unique[] = $value;
            }
        }

        return $unique;
    }

    /**
     * @param  list<string>  $values
     */
    private function scalarType(array $values): string
    {
        if ($values === []) {
            return 'string';
        }

        if ($this->every($values, fn (string $value): bool => preg_match('/^-?\d+$/', $value) === 1)) {
            return 'integer';
        }

        if ($this->every($values, fn (string $value): bool => $value === 'true' || $value === 'false')) {
            return 'boolean';
        }

        return 'string';
    }

    /**
     * @param  list<string>  $values
     * @param  callable(string): bool  $predicate
     */
    private function every(array $values, callable $predicate): bool
    {
        foreach ($values as $value) {
            if (! $predicate($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $values
     * @return list<int|bool|string>
     */
    private function castList(array $values, string $type): array
    {
        return array_map(
            fn (string $value): int|bool|string => $this->castValue($value, $type),
            $values,
        );
    }

    private function castValue(string $value, string $type): int|bool|string
    {
        if ($type === 'integer') {
            return (int) $value;
        }

        if ($type === 'boolean') {
            return $value === 'true';
        }

        return $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function requestBody(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $example = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => $this->inferSchema($example),
                    'example' => $example,
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function responseMap(string $docs): array
    {
        $extracted = $this->responses->extract($docs);

        if ($extracted === []) {
            $extracted = [
                '200' => [
                    'description' => 'Successful response',
                    'example' => null,
                ],
            ];
        }

        uksort($extracted, fn (string $left, string $right): int => (int) $left <=> (int) $right);

        $responses = [];

        foreach ($extracted as $code => $payload) {
            $description = $payload['description'] !== ''
                ? $payload['description']
                : 'Response '.$code;
            $response = ['description' => $description];

            if ($payload['example'] !== null) {
                $response['content'] = [
                    'application/json' => [
                        'schema' => $this->inferSchema($payload['example']),
                        'example' => $payload['example'],
                    ],
                ];
            }

            $responses[$code] = $response;
        }

        return $responses;
    }

    /**
     * @return array<string, mixed>
     */
    private function inferSchema(mixed $value): array
    {
        if (! is_array($value)) {
            return match (true) {
                is_int($value) => ['type' => 'integer'],
                is_float($value) => ['type' => 'number'],
                is_bool($value) => ['type' => 'boolean'],
                $value === null => ['nullable' => true],
                default => ['type' => 'string'],
            };
        }

        if (array_is_list($value)) {
            return [
                'type' => 'array',
                'items' => $value === []
                    ? ['type' => 'string']
                    : $this->inferSchema($value[0]),
            ];
        }

        $properties = [];

        foreach ($value as $key => $item) {
            $properties[(string) $key] = $this->inferSchema($item);
        }

        return [
            'type' => 'object',
            'properties' => $properties,
        ];
    }

    /**
     * @param  list<BrunoServer>  $servers
     * @return list<array{url: string, description: string}>
     */
    private function servers(array $servers): array
    {
        usort($servers, function (BrunoServer $left, BrunoServer $right): int {
            return [
                self::SERVER_ORDER[$left->name] ?? 99,
                $left->name,
            ] <=> [
                self::SERVER_ORDER[$right->name] ?? 99,
                $right->name,
            ];
        });

        $mapped = [];

        foreach ($servers as $server) {
            $mapped[] = [
                'url' => $server->url,
                'description' => $server->name,
            ];
        }

        return $mapped;
    }
}
