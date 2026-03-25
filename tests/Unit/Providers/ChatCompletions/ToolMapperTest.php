<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\ChatCompletions\ToolMapper;
use Atlasphp\Atlas\Providers\Tools\WebSearch;
use Atlasphp\Atlas\Tools\ToolDefinition;
use Illuminate\Support\Facades\Log;

it('maps tool definitions to nested function format', function () {
    $mapper = new ToolMapper;
    $tools = [
        new ToolDefinition('search', 'Search the web', [
            'type' => 'object',
            'properties' => ['query' => ['type' => 'string']],
            'required' => ['query'],
        ]),
    ];

    $result = $mapper->mapTools($tools);

    expect($result)->toHaveCount(1);
    expect($result[0]['type'])->toBe('function');
    expect($result[0]['function']['name'])->toBe('search');
    expect($result[0]['function']['description'])->toBe('Search the web');
    expect($result[0]['function']['parameters'])->toBe([
        'type' => 'object',
        'properties' => ['query' => ['type' => 'string']],
        'required' => ['query'],
    ]);
});

it('maps empty parameters to empty object', function () {
    $mapper = new ToolMapper;
    $tools = [new ToolDefinition('ping', 'Ping', [])];

    $result = $mapper->mapTools($tools);

    expect($result[0]['function']['parameters'])->toBeObject();
});

it('mapProviderTools returns empty array', function () {
    $mapper = new ToolMapper;

    expect($mapper->mapProviderTools([]))->toBe([]);
});

it('logs warning when provider tools are passed', function () {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message, array $context) {
            return str_contains($message, 'not supported')
                && $context['provider'] === 'chat_completions'
                && $context['tools'] === ['web_search'];
        });

    $mapper = new ToolMapper;
    $result = $mapper->mapProviderTools([new WebSearch]);

    expect($result)->toBe([]);
});

it('parses tool calls with id and nested function', function () {
    $mapper = new ToolMapper;
    $raw = [
        [
            'id' => 'call_abc',
            'function' => [
                'name' => 'search',
                'arguments' => '{"query":"test"}',
            ],
        ],
    ];

    $result = $mapper->parseToolCalls($raw);

    expect($result)->toHaveCount(1);
    expect($result[0]->id)->toBe('call_abc');
    expect($result[0]->name)->toBe('search');
    expect($result[0]->arguments)->toBe(['query' => 'test']);
});

it('throws on malformed JSON arguments', function () {
    $mapper = new ToolMapper;
    $raw = [
        [
            'id' => 'call_bad',
            'function' => [
                'name' => 'test',
                'arguments' => 'not-json',
            ],
        ],
    ];

    $mapper->parseToolCalls($raw);
})->throws(\JsonException::class);
