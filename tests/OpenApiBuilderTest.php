<?php

namespace Dotlib\BrunoDocs\Tests;

use Dotlib\BrunoDocs\Bruno\BruParser;
use Dotlib\BrunoDocs\Bruno\CollectionReader;
use Dotlib\BrunoDocs\OpenApi\DocsResponseExtractor;
use Dotlib\BrunoDocs\OpenApi\OpenApiBuilder;
use PHPUnit\Framework\TestCase;

class OpenApiBuilderTest extends TestCase
{
    public function test_build_maps_the_fixture_collection(): void
    {
        $spec = $this->specFrom(__DIR__.'/fixtures/collection');
        $encoded = json_encode($spec);

        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('super-secret-token', $encoded);
        $this->assertStringNotContainsString('production-secret-token', $encoded);
        $this->assertStringNotContainsString('alpha-secret-token', $encoded);
        $this->assertSame('Fixture API', $spec['info']['title']);
        $this->assertSame([
            ['url' => 'http://localhost:8086', 'description' => 'local'],
            ['url' => 'https://baymax.example', 'description' => 'production'],
            ['url' => 'https://alpha.example', 'description' => 'alpha'],
        ], $spec['servers']);

        $list = $spec['paths']['/api/medicaments']['get'];
        $this->assertSame(['bearerAuth' => []], $list['security'][0]);
        $this->assertSame(100, $this->parameter($list, 'take')['example']);
        $this->assertSame(['NAME', 'COMPANY', 'RELEVANCE'], $this->parameter($list, 'sort')['schema']['enum']);
        $this->assertSame('NAME', $this->parameter($list, 'sort')['example']);
        $this->assertSame([0, 1], $this->parameter($list, 'in_document')['schema']['enum']);
        $this->assertSame(
            ['52bdfc69-2e2a-4558-a075-baab9660d566', '859f416b-34fa-4158-9cb3-67f1f01c9b18'],
            $this->parameter($list, 'ids[]')['example'],
        );
        $this->assertSame(1, $list['responses']['200']['content']['application/json']['example']['total']);
        $this->assertSame('quando take ou skip são inválidos', $list['responses']['422']['description']);

        $update = $spec['paths']['/api/medicaments/{medicament}']['put'];
        $this->assertSame(['bearerAuth' => []], $update['security'][0]);
        $this->assertSame('591c0377-08d0-4259-8cf3-015d901c2b4c', $update['parameters'][0]['example']);
        $this->assertSame(
            ['2c853eef-7258-4540-93b8-45692530935b'],
            $update['requestBody']['content']['application/json']['example']['active_principles'],
        );
        $this->assertStringContainsString('quando o id não é um uuid', $update['responses']['404']['description']);

        $this->assertArrayHasKey('/api/medicaments/batch', $spec['paths']);
        $this->assertSame(
            'Medicaments / Batch',
            $spec['paths']['/api/medicaments/batch']['put']['tags'][0],
        );
        $this->assertSame('put_medicaments_batch_update', $spec['paths']['/api/medicaments/batch']['put']['operationId']);

        $show = $spec['paths']['/api/active-principles/{active-principle}']['get'];
        $this->assertSame('a6aed589-af9b-4651-9e9e-a8380e5ea6ec', $show['parameters'][0]['example']);

        $this->assertArrayNotHasKey('requestBody', $spec['paths']['/api/medicaments/broken']['post']);
        $this->assertArrayNotHasKey('security', $spec['paths']['/api/up']['get']);
        $this->assertArrayNotHasKey('security', $spec['paths']['/api/medicaments/broken']['post']);
    }

    public function test_title_override_replaces_the_collection_name(): void
    {
        $spec = $this->specFrom(__DIR__.'/fixtures/collection', 'Custom Title');

        $this->assertSame('Custom Title', $spec['info']['title']);
    }

    public function test_real_baymax_collection_builds_without_leaking_placeholders_as_servers(): void
    {
        $root = dirname(__DIR__, 3).'/api-docs';

        if (! is_dir($root)) {
            $this->markTestSkipped('Baymax api-docs collection is not available.');
        }

        $spec = $this->specFrom($root);

        $this->assertSame('Baymax API', $spec['info']['title']);
        $this->assertArrayHasKey('/api/medicaments', $spec['paths']);
        $this->assertArrayHasKey('/api/medicaments/{medicament}', $spec['paths']);
        $this->assertArrayHasKey('get', $spec['paths']['/api/medicaments/{medicament}']);
        $this->assertArrayHasKey('put', $spec['paths']['/api/medicaments/{medicament}']);
        $this->assertArrayHasKey('/api/active-principles/{active-principle}', $spec['paths']);
        $this->assertArrayHasKey('/api/active-principles/batch', $spec['paths']);
        $this->assertSame(
            ['NAME', 'COMPANY', 'RELEVANCE'],
            $this->parameter($spec['paths']['/api/medicaments']['get'], 'sort')['schema']['enum'],
        );
        $this->assertSame([
            'http://localhost:8086',
            'https://baymax-dev.dotlib.com.br',
            'https://baymax-homolog.dotlib.com.br',
            'https://baymax.dotlib.com.br',
        ], array_column($spec['servers'], 'url'));
        $this->assertArrayHasKey('requestBody', $spec['paths']['/api/active-principles']['post']);
    }

    /**
     * @return array<string, mixed>
     */
    private function specFrom(string $root, ?string $title = null): array
    {
        $reader = new CollectionReader(new BruParser);
        $builder = new OpenApiBuilder(new DocsResponseExtractor);

        return $builder->build($reader->read($root), $title);
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function parameter(array $operation, string $name): array
    {
        foreach ($operation['parameters'] as $parameter) {
            if ($parameter['name'] === $name) {
                return $parameter;
            }
        }

        $this->fail("Parameter [{$name}] was not found.");
    }
}
