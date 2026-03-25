<?php

declare(strict_types=1);

use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Providers\Anthropic\ToolMapper;
use Atlasphp\Atlas\Providers\Tools\WebSearch;
use Atlasphp\Atlas\Tools\ToolDefinition;
use Illuminate\Support\Facades\Log;

it('maps tools to input_schema format', function () {
    $mapper = new ToolMapper;

    $result = $mapper->mapTools([
        new ToolDefinition('search', 'Search the web', ['type' => 'object', 'properties' => ['query' => ['type' => 'string']]]),
    ]);

    expect($result)->toHaveCount(1);
    expect($result[0]['name'])->toBe('search');
    expect($result[0]['description'])->toBe('Search the web');
    expect($result[0]['input_schema'])->toBe(['type' => 'object', 'properties' => ['query' => ['type' => 'string']]]);
});

it('maps tools with empty parameters to object with empty properties', function () {
    $mapper = new ToolMapper;

    $result = $mapper->mapTools([
        new ToolDefinition('ping', 'Ping server', []),
    ]);

    expect($result[0]['input_schema'])->toEqual(['type' => 'object', 'properties' => (object) []]);
});

it('parses tool_use blocks into ToolCall objects', function () {
    $mapper = new ToolMapper;

    $result = $mapper->parseToolCalls([
        ['id' => 'toolu_abc123', 'name' => 'search', 'input' => ['query' => 'test']],
    ]);

    expect($result)->toHaveCount(1);
    expect($result[0])->toBeInstanceOf(ToolCall::class);
    expect($result[0]->id)->toBe('toolu_abc123');
    expect($result[0]->name)->toBe('search');
    expect($result[0]->arguments)->toBe(['query' => 'test']);
});

it('maps empty tools returns empty', function () {
    $mapper = new ToolMapper;

    $result = $mapper->mapTools([]);

    expect($result)->toBe([]);
});

it('returns empty array for provider tools', function () {
    $mapper = new ToolMapper;

    $result = $mapper->mapProviderTools([]);

    expect($result)->toBe([]);
});

it('parses empty tool calls returns empty', function () {
    $mapper = new ToolMapper;

    $result = $mapper->parseToolCalls([]);

    expect($result)->toBe([]);
});

it('logs warning when provider tools are passed', function () {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message, array $context) {
            return str_contains($message, 'not supported')
                && $context['provider'] === 'anthropic'
                && $context['tools'] === ['web_search'];
        });

    $mapper = new ToolMapper;
    $result = $mapper->mapProviderTools([new WebSearch]);

    expect($result)->toBe([]);
});

it('uses fallback for missing keys in tool_use blocks', function () {
    $mapper = new ToolMapper;

    $result = $mapper->parseToolCalls([
        ['type' => 'tool_use'],
    ]);

    expect($result)->toHaveCount(1);
    expect($result[0]->id)->toBe('');
    expect($result[0]->name)->toBe('');
    expect($result[0]->arguments)->toBe([]);
});
