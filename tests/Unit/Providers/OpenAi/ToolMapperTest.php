<?php

declare(strict_types=1);

use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Providers\OpenAi\ToolMapper;
use Atlasphp\Atlas\Providers\Tools\WebSearch;
use Atlasphp\Atlas\Tools\ToolDefinition;

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

    $tools = [new WebSearch(maxResults: 5)];

    $result = $mapper->mapProviderTools($tools);

    expect($result)->toHaveCount(1);
    expect($result[0]['type'])->toBe('web_search');
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

it('throws on malformed JSON arguments', function () {
    $mapper = new ToolMapper;

    $raw = [
        ['call_id' => 'call_abc', 'name' => 'test', 'arguments' => 'not-json'],
    ];

    $mapper->parseToolCalls($raw);
})->throws(JsonException::class);
