<?php

namespace Dotlib\BrunoDocs\Bruno;

use RuntimeException;

/**
 * Loads a Bruno collection directory into requests, tags and servers.
 */
final class CollectionReader
{
    private const METHODS = ['get', 'post', 'put', 'patch', 'delete', 'head', 'options'];

    public function __construct(private readonly BruParser $parser) {}

    /**
     * Read every request, folder tag and environment server in the collection.
     *
     * @throws RuntimeException
     */
    public function read(string $root): BrunoCollection
    {
        $root = rtrim($root, '/');
        $files = $this->bruFiles($root);
        $folders = $this->folderMeta($files);
        $defaultAuth = $this->defaultAuth($root);
        $requests = [];

        foreach ($files as $relative => $absolute) {
            if ($this->isIgnoredRequest($relative)) {
                continue;
            }

            foreach ($this->requestsFromFile($absolute, $relative, $folders, $defaultAuth) as $request) {
                $requests[] = $request;
            }
        }

        return new BrunoCollection(
            name: $this->collectionName($root),
            defaultAuth: $defaultAuth,
            servers: $this->servers($root),
            requests: $requests,
        );
    }

    /**
     * Collection title from bruno.json, or "API" when the file has no name.
     *
     * @throws RuntimeException
     */
    public function collectionName(string $root): string
    {
        $path = rtrim($root, '/').'/bruno.json';

        if (! is_file($path)) {
            return 'API';
        }

        $decoded = json_decode($this->contents($path), true);
        $name = is_array($decoded) ? ($decoded['name'] ?? null) : null;

        if (! is_string($name) || $name === '') {
            return 'API';
        }

        return $name;
    }

    /**
     * Fingerprint of collection files so a cached spec can be invalidated.
     */
    public function fingerprint(string $root): string
    {
        $root = rtrim($root, '/');
        $parts = [];

        foreach ($this->bruFiles($root) as $relative => $absolute) {
            $parts[] = $relative.':'.(string) filemtime($absolute);
        }

        $environment = $root.'/environments';

        if (is_dir($environment)) {
            foreach (scandir($environment) ?: [] as $filename) {
                if (! str_ends_with($filename, '.bru')) {
                    continue;
                }

                $parts[] = 'environments/'.$filename.':'.(string) filemtime($environment.'/'.$filename);
            }
        }

        $json = $root.'/bruno.json';

        if (is_file($json)) {
            $parts[] = 'bruno.json:'.(string) filemtime($json);
        }

        sort($parts);

        return hash('sha256', implode('|', $parts));
    }

    /**
     * @param  array<string, string>  $files
     * @return array<string, array{name: string, seq: int}>
     */
    private function folderMeta(array $files): array
    {
        $folders = [];

        foreach ($files as $relative => $absolute) {
            if (basename($relative) !== 'folder.bru' || $this->isEnvironment($relative)) {
                continue;
            }

            $blocks = $this->parser->parse($this->contents($absolute));
            $meta = $this->parser->parseDictionary($blocks['meta'] ?? '');
            $directory = dirname($relative);
            $folders[$directory === '.' ? '' : $directory] = [
                'name' => $meta['name'] ?? $this->humanize(basename($directory === '.' ? '' : $directory)),
                'seq' => (int) ($meta['seq'] ?? 0),
            ];
        }

        return $folders;
    }

    /**
     * @param  array<string, array{name: string, seq: int}>  $folders
     * @return list<BrunoRequest>
     */
    private function requestsFromFile(string $absolute, string $relative, array $folders, ?string $defaultAuth): array
    {
        $blocks = $this->parser->parse($this->contents($absolute));
        $meta = $this->parser->parseDictionary($blocks['meta'] ?? '');
        $directory = dirname($relative);
        $tag = $this->tagFor($directory === '.' ? '' : $directory, $folders);
        $requests = [];

        foreach (self::METHODS as $method) {
            if (! isset($blocks[$method])) {
                continue;
            }

            $fields = $this->parser->parseDictionary($blocks[$method]);
            $url = $fields['url'] ?? '';
            $requests[] = new BrunoRequest(
                name: $meta['name'] ?? pathinfo($relative, PATHINFO_FILENAME),
                sequence: (int) ($meta['seq'] ?? 0),
                method: $method,
                url: $url,
                auth: $this->resolveAuth($fields['auth'] ?? 'inherit', $defaultAuth),
                docs: $blocks['docs'] ?? '',
                body: $this->body($fields, $blocks),
                query: $this->query($blocks, $url),
                tag: $tag['name'],
                tagSequence: $tag['tagSequence'],
                nestedSequence: $tag['nestedSequence'],
            );
        }

        return $requests;
    }

    /**
     * @param  array<string, string>  $fields
     * @param  array<string, string>  $blocks
     */
    private function body(array $fields, array $blocks): ?string
    {
        if (($fields['body'] ?? 'none') === 'none' || ! isset($blocks['body:json'])) {
            return null;
        }

        return $blocks['body:json'];
    }

    /**
     * @param  array<string, string>  $blocks
     * @return list<array{name: string, value: string, disabled: bool}>
     */
    private function query(array $blocks, string $url): array
    {
        if (isset($blocks['params:query'])) {
            return $this->parser->parseQuery($blocks['params:query']);
        }

        return $this->queryFromUrl($url);
    }

    /**
     * @return list<array{name: string, value: string, disabled: bool}>
     */
    private function queryFromUrl(string $url): array
    {
        $pieces = explode('?', $url, 2);

        if (! isset($pieces[1]) || $pieces[1] === '') {
            return [];
        }

        $parsed = [];
        parse_str($pieces[1], $parsed);
        $rows = [];

        foreach ($parsed as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $rows[] = [
                        'name' => $name.'[]',
                        'value' => (string) $item,
                        'disabled' => false,
                    ];
                }

                continue;
            }

            $rows[] = [
                'name' => (string) $name,
                'value' => (string) $value,
                'disabled' => false,
            ];
        }

        return $rows;
    }

    private function resolveAuth(string $auth, ?string $defaultAuth): ?string
    {
        if ($auth === 'inherit') {
            $auth = $defaultAuth ?? 'none';
        }

        if ($auth === '' || $auth === 'none') {
            return null;
        }

        return $auth;
    }

    /**
     * @param  array<string, array{name: string, seq: int}>  $folders
     * @return array{name: string, tagSequence: int, nestedSequence: int}
     */
    private function tagFor(string $directory, array $folders): array
    {
        if ($directory === '') {
            return [
                'name' => 'General',
                'tagSequence' => 0,
                'nestedSequence' => 0,
            ];
        }

        $names = [];
        $tagSequence = 0;
        $nestedSequence = 0;
        $path = '';

        foreach (explode('/', $directory) as $index => $segment) {
            $path = $path === '' ? $segment : $path.'/'.$segment;
            $meta = $folders[$path] ?? null;
            $names[] = $meta['name'] ?? $this->humanize($segment);

            if ($index === 0) {
                $tagSequence = $meta['seq'] ?? 0;

                continue;
            }

            $nestedSequence = $meta['seq'] ?? 0;
        }

        return [
            'name' => implode(' / ', $names),
            'tagSequence' => $tagSequence,
            'nestedSequence' => $nestedSequence,
        ];
    }

    /**
     * @return list<BrunoServer>
     */
    private function servers(string $root): array
    {
        $directory = $root.'/environments';

        if (! is_dir($directory)) {
            return [];
        }

        $servers = [];

        foreach (scandir($directory) ?: [] as $filename) {
            if (! str_ends_with($filename, '.bru')) {
                continue;
            }

            $blocks = $this->parser->parse($this->contents($directory.'/'.$filename));
            $vars = $this->parser->parseDictionary($blocks['vars'] ?? '');
            $url = rtrim($vars['baseUrl'] ?? '', '/');

            if ($url === '' || preg_match('#^https?://#i', $url) !== 1) {
                continue;
            }

            $servers[] = new BrunoServer(
                name: basename($filename, '.bru'),
                url: $url,
            );
        }

        return $servers;
    }

    private function defaultAuth(string $root): ?string
    {
        $path = $root.'/collection.bru';

        if (! is_file($path)) {
            return null;
        }

        $blocks = $this->parser->parse($this->contents($path));
        $mode = $this->parser->parseDictionary($blocks['auth'] ?? '')['mode'] ?? 'none';

        if ($mode === '' || $mode === 'none') {
            return null;
        }

        return $mode;
    }

    /**
     * @return array<string, string>
     */
    private function bruFiles(string $root): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        $files = [];

        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'bru') {
                continue;
            }

            $relative = str_replace(DIRECTORY_SEPARATOR, '/', $iterator->getSubPathname());

            if ($this->isEnvironment($relative)) {
                continue;
            }

            $files[$relative] = $file->getPathname();
        }

        ksort($files);

        return $files;
    }

    private function isIgnoredRequest(string $relative): bool
    {
        return $relative === 'collection.bru' || basename($relative) === 'folder.bru';
    }

    private function isEnvironment(string $relative): bool
    {
        return $relative === 'environments' || str_starts_with($relative, 'environments/');
    }

    private function humanize(string $segment): string
    {
        if ($segment === '' || $segment === '.') {
            return 'General';
        }

        return ucfirst(str_replace(['-', '_'], ' ', $segment));
    }

    /**
     * @throws RuntimeException
     */
    private function contents(string $path): string
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read Bruno file [{$path}].");
        }

        return $contents;
    }
}
