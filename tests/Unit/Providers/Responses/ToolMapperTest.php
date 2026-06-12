<?php

declare(strict_types=1);

use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Providers\Responses\ToolMapper;
use Atlasphp\Atlas\Providers\Tools\CodeInterpreter;
use Atlasphp\Atlas\Providers\Tools\FileSearch;
use Atlasphp\Atlas\Providers\Tools\WebSearch;
use Atlasphp\Atlas\Tools\ToolChoice;
use Atlasphp\Atlas\Tools\ToolDefinition;

it('maps tool choice to the Responses API shape', function (ToolChoice $choice, mixed $expected) {
    expect((new ToolMapper)->mapToolChoice($choice))->toBe(['tool_choice' => $expected]);
})->with([
    'auto' => [ToolChoice::auto(), 'auto'],
    'required' => [ToolChoice::required(), 'required'],
    'none' => [ToolChoice::none(), 'none'],
    'specific tool' => [ToolChoice::tool('get_weather'), ['type' => 'function', 'name' => 'get_weather']],
]);

it('maps tool definitions to flat function format', function () {
    $mapper = new ToolMapper;

    $tools = [
        new ToolDefinition('search', 'Search the web', ['type' => 'object', 'properties' => ['q' => ['type' => 'string']]]),
    ];

    $result = $mapper->mapTools($tools);

    expect($result)->toHaveCount(1);
    expect($result[0]['type'])->toBe('function');
    expect($result[0]['name'])->toBe('search');
    expect($result[0]['description'])->toBe('Search the web');
    expect($result[0]['parameters'])->toBe(['type' => 'object', 'properties' => ['q' => ['type' => 'string']]]);
    expect($result[0]['strict'])->toBeTrue();
});

it('maps empty parameters to empty object', function () {
    $mapper = new ToolMapper;

    $tools = [new ToolDefinition('ping', 'Ping', [])];

    $result = $mapper->mapTools($tools);

    expect($result[0]['parameters'])->toBeObject();
});

it('maps provider tools via toArray', function () {
    $mapper = new ToolMapper;

    $tools = [new WebSearch];

    $result = $mapper->mapProviderTools($tools);

    expect($result)->toHaveCount(1);
    expect($result[0]['type'])->toBe('web_search');
    expect($result[0])->not->toHaveKey('filters');
});

it('returns non-web_search provider tools unchanged (mapProviderTool passthrough)', function () {
    $mapper = new ToolMapper;

    // file_search and code_interpreter are NOT web_search, so they hit the
    // `return $payload` branch — emitted verbatim from toArray(), no `filters`.
    $result = $mapper->mapProviderTools([
        new FileSearch(stores: ['vs_1'], maxResults: 5),
        new CodeInterpreter,
    ]);

    expect($result[0])->toBe([
        'type' => 'file_search',
        'vector_store_ids' => ['vs_1'],
        'max_num_results' => 5,
    ]);
    expect($result[0])->not->toHaveKey('filters');

    expect($result[1])->toBe([
        'type' => 'code_interpreter',
        'container' => ['type' => 'auto'],
    ]);
    expect($result[1])->not->toHaveKey('filters');
});

it('passes web_search options through while nesting domain filters', function () {
    $mapper = new ToolMapper;

    $result = $mapper->mapProviderTools([
        new WebSearch(allowedDomains: ['laravel.com'], options: ['search_context_size' => 'high']),
    ]);

    // Domain scoping is nested under `filters`; other attributes pass through.
    expect($result[0])->toBe([
        'type' => 'web_search',
        'search_context_size' => 'high',
        'filters' => ['allowed_domains' => ['laravel.com']],
    ]);
});

it('nests web_search allowed domains under filters', function () {
    $mapper = new ToolMapper;

    $result = $mapper->mapProviderTools([
        new WebSearch(allowedDomains: ['laravel.com'], blockedDomains: ['spam.example']),
    ]);

    expect($result[0]['type'])->toBe('web_search');
    // Both domain lists nest under `filters` (Responses API supports each).
    expect($result[0]['filters'])->toBe([
        'allowed_domains' => ['laravel.com'],
        'blocked_domains' => ['spam.example'],
    ]);
    expect($result[0])->not->toHaveKey('allowed_domains');
    expect($result[0])->not->toHaveKey('blocked_domains');
});

it('parses function call items into ToolCall objects', function () {
    $mapper = new ToolMapper;

    $raw = [
        ['call_id' => 'call_abc', 'name' => 'search', 'arguments' => '{"q":"test"}'],
    ];

    $result = $mapper->parseToolCalls($raw);

    expect($result)->toHaveCount(1);
    expect($result[0])->toBeInstanceOf(ToolCall::class);
    expect($result[0]->id)->toBe('call_abc');
    expect($result[0]->name)->toBe('search');
    expect($result[0]->arguments)->toBe(['q' => 'test']);
});

it('uses call_id verbatim even when it is a bare numeric index', function () {
    // The neutral base does no numeric special-casing — that is xAI-specific.
    $mapper = new ToolMapper;

    $result = $mapper->parseToolCalls([
        ['call_id' => '0', 'name' => 'generate_image', 'arguments' => '{"prompt":"test"}', 'id' => 'fc_abc123_0'],
    ]);

    expect($result[0]->id)->toBe('0');
});

it('uses call_id when it is a proper identifier', function () {
    $mapper = new ToolMapper;

    $raw = [
        [
            'call_id' => 'call_abc123',
            'name' => 'search',
            'arguments' => '{"q":"test"}',
            'id' => 'fc_xyz789_0',
        ],
    ];

    $result = $mapper->parseToolCalls($raw);

    expect($result[0]->id)->toBe('call_abc123');
});

it('falls back to id when call_id is empty', function () {
    $mapper = new ToolMapper;

    $raw = [
        [
            'call_id' => '',
            'name' => 'search',
            'arguments' => '{}',
            'id' => 'fc_fallback_0',
        ],
    ];

    $result = $mapper->parseToolCalls($raw);

    expect($result[0]->id)->toBe('fc_fallback_0');
});

it('returns empty string when both call_id and id are missing', function () {
    $mapper = new ToolMapper;

    $raw = [
        ['name' => 'search', 'arguments' => '{}'],
    ];

    $result = $mapper->parseToolCalls($raw);

    expect($result[0]->id)->toBe('');
});

it('throws on malformed JSON arguments', function () {
    $mapper = new ToolMapper;

    $raw = [
        ['call_id' => 'call_abc', 'name' => 'test', 'arguments' => 'not-json'],
    ];

    $mapper->parseToolCalls($raw);
})->throws(JsonException::class);
