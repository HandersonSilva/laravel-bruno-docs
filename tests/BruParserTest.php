<?php

namespace Dotlib\BrunoDocs\Tests;

use Dotlib\BrunoDocs\Bruno\BruParser;
use PHPUnit\Framework\TestCase;

class BruParserTest extends TestCase
{
    private BruParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new BruParser;
    }

    public function test_parse_keeps_nested_json_and_bracket_blocks(): void
    {
        $document = <<<'BRU'
meta {
  name: Store
  seq: 3
}

body:json {
  {
    "items": [{ "name": "Princípio {1}" }]
  }
}

vars:secret [
  api_token
]

docs {
  Resposta 200:
  {
    "id": "uuid"
  }
}
BRU;

        $blocks = $this->parser->parse($document);

        $this->assertSame('Store', $this->parser->parseDictionary($blocks['meta'])['name']);
        $this->assertSame(3, (int) $this->parser->parseDictionary($blocks['meta'])['seq']);
        $this->assertSame('api_token', trim($blocks['vars:secret']));
        $decoded = json_decode($blocks['body:json'], true);
        $this->assertSame('Princípio {1}', $decoded['items'][0]['name']);
        $this->assertStringContainsString('"id": "uuid"', $blocks['docs']);
    }

    public function test_parse_query_marks_tilde_rows_and_keeps_duplicates(): void
    {
        $rows = $this->parser->parseQuery(<<<'QUERY'
take: 100
sort: NAME
~sort: COMPANY
~sort: RELEVANCE
ids[]: 52bdfc69-2e2a-4558-a075-baab9660d566
ids[]: 859f416b-34fa-4158-9cb3-67f1f01c9b18
QUERY);

        $this->assertSame([
            ['name' => 'take', 'value' => '100', 'disabled' => false],
            ['name' => 'sort', 'value' => 'NAME', 'disabled' => false],
            ['name' => 'sort', 'value' => 'COMPANY', 'disabled' => true],
            ['name' => 'sort', 'value' => 'RELEVANCE', 'disabled' => true],
            ['name' => 'ids[]', 'value' => '52bdfc69-2e2a-4558-a075-baab9660d566', 'disabled' => false],
            ['name' => 'ids[]', 'value' => '859f416b-34fa-4158-9cb3-67f1f01c9b18', 'disabled' => false],
        ], $rows);
    }
}
