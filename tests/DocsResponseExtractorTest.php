<?php

namespace Dotlib\BrunoDocs\Tests;

use Dotlib\BrunoDocs\OpenApi\DocsResponseExtractor;
use PHPUnit\Framework\TestCase;

class DocsResponseExtractorTest extends TestCase
{
    public function test_extract_status_examples_without_treating_prose_numbers_as_codes(): void
    {
        $docs = <<<'TXT'
Lista.

- `take` (≥ 0; ausente ou 0 = sem limite)

Resposta 200:
{
  "total": 1
}

- 401 {"message":"Unauthenticated."}
- 404 {"message":"Medicament not found."} quando o uuid não existe
- 404 quando o id não é um uuid
TXT;

        $responses = (new DocsResponseExtractor)->extract($docs);

        $this->assertSame([200, 401, 404], array_keys($responses));
        $this->assertSame(['total' => 1], $responses['200']['example']);
        $this->assertSame(['message' => 'Unauthenticated.'], $responses['401']['example']);
        $this->assertSame(['message' => 'Medicament not found.'], $responses['404']['example']);
        $this->assertStringContainsString('quando o uuid não existe', $responses['404']['description']);
        $this->assertStringContainsString('quando o id não é um uuid', $responses['404']['description']);
        $this->assertArrayNotHasKey('100', $responses);
    }
}
