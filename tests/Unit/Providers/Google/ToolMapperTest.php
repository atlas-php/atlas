<?php

declare(strict_types=1);

use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Providers\Google\GoogleToolCall;
use Atlasphp\Atlas\Providers\Google\ToolMapper;
use Atlasphp\Atlas\Providers\Tools\CodeExecution;
use Atlasphp\Atlas\Providers\Tools\GoogleSearch;
use Atlasphp\Atlas\Providers\Tools\WebSearch;
use Atlasphp\Atlas\Tools\ToolChoice;
use Atlasphp\Atlas\Tools\ToolDefinition;

it('maps tool choice to Gemini tool_config', function (ToolChoice $choice, array $expectedConfig) {
    expect((new ToolMapper)->mapToolChoice($choice))
        ->toBe(['tool_config' => ['function_calling_config' => $expectedConfig]]);
})->with([
    'auto' => [ToolChoice::auto(), ['mode' => 'AUTO']],
    'required' => [ToolChoice::required(), ['mode' => 'ANY']],
    'none' => [ToolChoice::none(), ['mode' => 'NONE']],
    'specific tool' => [ToolChoice::tool('get_weather'), ['mode' => 'ANY', 'allowed_function_names' => ['get_weather']]],
]);

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

it('removes unsupported Gemini schema keywords from tool parameters', function () {
    $mapper = new ToolMapper;

    $result = $mapper->mapTools([
        new ToolDefinition('search', 'Search the web', [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'query' => [
                    'type' => 'string',
                ],
                'filters' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'status' => ['type' => 'string'],
                    ],
                ],
                'additionalProperties' => [
                    'type' => 'string',
                    'description' => 'A regular tool argument that happens to share a JSON Schema keyword name.',
                ],
            ],
        ]),
    ]);

    expect($result[0]['parameters'])->toBe([
        'type' => 'object',
        'properties' => [
            'query' => [
                'type' => 'string',
            ],
            'filters' => [
                'type' => 'object',
                'properties' => [
                    'status' => ['type' => 'string'],
                ],
            ],
            'additionalProperties' => [
                'type' => 'string',
                'description' => 'A regular tool argument that happens to share a JSON Schema keyword name.',
            ],
        ],
    ]);
});

it('keeps empty Gemini object properties encoded as objects', function () {
    $mapper = new ToolMapper;

    $result = $mapper->mapTools([
        new ToolDefinition('empty', 'Inspect an empty object', [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [],
        ]),
        new ToolDefinition('inspect', 'Inspect an empty object', [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'payload' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [],
                ],
            ],
        ]),
    ]);

    expect($result[0]['parameters'])->toEqual([
        'type' => 'object',
        'properties' => (object) [],
    ]);

    expect($result[1]['parameters'])->toEqual([
        'type' => 'object',
        'properties' => [
            'payload' => [
                'type' => 'object',
                'properties' => (object) [],
            ],
        ],
    ]);
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

it('preserves thought signatures from functionCall parts', function () {
    $mapper = new ToolMapper;

    $result = $mapper->parseToolCalls([
        [
            'functionCall' => ['id' => 'call-1', 'name' => 'search', 'args' => ['query' => 'test']],
            'thoughtSignature' => 'signature-from-gemini',
        ],
    ]);

    expect($result[0])->toBeInstanceOf(GoogleToolCall::class);
    expect($result[0]->thoughtSignature)->toBe('signature-from-gemini');
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

it('defaults the name to an empty string when functionCall has no name', function () {
    $mapper = new ToolMapper;

    $result = $mapper->parseToolCalls([
        ['functionCall' => ['args' => ['query' => 'test']]],
    ]);

    expect($result)->toHaveCount(1);
    expect($result[0])->toBeInstanceOf(GoogleToolCall::class);
    expect($result[0]->name)->toBe('');
    expect($result[0]->arguments)->toBe(['query' => 'test']);
});

it('degrades gracefully when a part has no functionCall key', function () {
    $mapper = new ToolMapper;

    $result = $mapper->parseToolCalls([
        ['thoughtSignature' => 'sig-only'],
    ]);

    expect($result)->toHaveCount(1);
    expect($result[0]->name)->toBe('');
    expect($result[0]->id)->toBe('gemini_call_0');
    expect($result[0]->arguments)->toBe([]);
    expect($result[0]->thoughtSignature)->toBe('sig-only');
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

    $result = $mapper->mapProviderTools([new WebSearch(allowedDomains: ['laravel.com'])]);

    expect($result)->toHaveCount(1);
    expect($result[0]['type'])->toBe('web_search');
    expect($result[0]['allowed_domains'])->toBe(['laravel.com']);
});
