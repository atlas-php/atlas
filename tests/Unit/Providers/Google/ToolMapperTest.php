<?php

declare(strict_types=1);

use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Providers\Google\ToolMapper;
use Atlasphp\Atlas\Providers\Tools\CodeExecution;
use Atlasphp\Atlas\Providers\Tools\GoogleSearch;
use Atlasphp\Atlas\Providers\Tools\WebSearch;
use Atlasphp\Atlas\Tools\ToolDefinition;

it('maps tools to function_declarations format', function () {
    $mapper = new ToolMapper;

    $result = $mapper->mapTools([
        new ToolDefinition('search', 'Search the web', ['type' => 'object', 'properties' => ['query' => ['type' => 'string']]]),
    ]);

    expect($result)->toHaveCount(1);
    expect($result[0]['name'])->toBe('search');
    expect($result[0]['description'])->toBe('Search the web');
    expect($result[0]['parameters'])->toBe(['type' => 'object', 'properties' => ['query' => ['type' => 'string']]]);
});

it('maps tools with empty parameters to object with empty properties', function () {
    $mapper = new ToolMapper;

    $result = $mapper->mapTools([
        new ToolDefinition('ping', 'Ping server', []),
    ]);

    expect($result[0]['parameters'])->toEqual(['type' => 'object', 'properties' => (object) []]);
});

it('parses tool calls extracting name and args from functionCall parts', function () {
    $mapper = new ToolMapper;

    $result = $mapper->parseToolCalls([
        ['functionCall' => ['name' => 'search', 'args' => ['query' => 'test']]],
    ]);

    expect($result)->toHaveCount(1);
    expect($result[0])->toBeInstanceOf(ToolCall::class);
    expect($result[0]->name)->toBe('search');
    expect($result[0]->arguments)->toBe(['query' => 'test']);
});

it('generates fallback ID when no id field', function () {
    $mapper = new ToolMapper;

    $result = $mapper->parseToolCalls([
        ['functionCall' => ['name' => 'search', 'args' => []]],
    ]);

    expect($result[0]->id)->toBe('gemini_call_0');
});

it('uses id field when present', function () {
    $mapper = new ToolMapper;

    $result = $mapper->parseToolCalls([
        ['functionCall' => ['id' => 'custom_id_123', 'name' => 'search', 'args' => []]],
    ]);

    expect($result[0]->id)->toBe('custom_id_123');
});

it('maps GoogleSearch to gemini format via mapper', function () {
    $mapper = new ToolMapper;

    $result = $mapper->mapProviderTools([new GoogleSearch]);

    expect($result)->toHaveCount(1);
    expect($result[0])->toHaveKey('google_search');
    expect($result[0]['google_search'])->toBeInstanceOf(stdClass::class);
});

it('maps CodeExecution to gemini format via mapper', function () {
    $mapper = new ToolMapper;

    $result = $mapper->mapProviderTools([new CodeExecution]);

    expect($result)->toHaveCount(1);
    expect($result[0])->toHaveKey('code_execution');
    expect($result[0]['code_execution'])->toBeInstanceOf(stdClass::class);
});

it('maps non-gemini provider tools via toArray', function () {
    $mapper = new ToolMapper;

    $result = $mapper->mapProviderTools([new WebSearch(maxResults: 3)]);

    expect($result)->toHaveCount(1);
    expect($result[0]['type'])->toBe('web_search');
    expect($result[0]['max_results'])->toBe(3);
});
