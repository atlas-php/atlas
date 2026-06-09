<?php

declare(strict_types=1);

use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Providers\Anthropic\ToolMapper;
use Atlasphp\Atlas\Providers\Tools\CodeInterpreter;
use Atlasphp\Atlas\Providers\Tools\ProviderTool;
use Atlasphp\Atlas\Providers\Tools\ProviderToolRegistry;
use Atlasphp\Atlas\Providers\Tools\WebFetch;
use Atlasphp\Atlas\Providers\Tools\WebSearch;
use Atlasphp\Atlas\Tools\ToolChoice;
use Atlasphp\Atlas\Tools\ToolDefinition;
use Illuminate\Support\Facades\Log;

it('maps tool choice to the Anthropic object shape', function (ToolChoice $choice, array $expected) {
    expect((new ToolMapper)->mapToolChoice($choice))->toBe(['tool_choice' => $expected]);
})->with([
    'auto' => [ToolChoice::auto(), ['type' => 'auto']],
    'required' => [ToolChoice::required(), ['type' => 'any']],
    'none' => [ToolChoice::none(), ['type' => 'none']],
    'specific tool' => [ToolChoice::tool('get_weather'), ['type' => 'tool', 'name' => 'get_weather']],
]);

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

it('returns empty array for no provider tools', function () {
    $mapper = new ToolMapper;

    $result = $mapper->mapProviderTools([]);

    expect($result)->toBe([]);
});

it('maps web_search to Anthropic versioned server tool, preserving attributes', function () {
    $mapper = new ToolMapper;

    $result = $mapper->mapProviderTools([
        new WebSearch(allowedDomains: ['laravel.com'], options: ['max_uses' => 3]),
    ]);

    expect($result[0])->toBe([
        'type' => 'web_search_20250305',
        'allowed_domains' => ['laravel.com'],
        'max_uses' => 3,
        'name' => 'web_search',
    ]);
});

it('maps web_fetch to Anthropic versioned server tool', function () {
    $mapper = new ToolMapper;

    $result = $mapper->mapProviderTools([new WebFetch(['max_uses' => 2])]);

    expect($result[0])->toBe([
        'type' => 'web_fetch_20250910',
        'max_uses' => 2,
        'name' => 'web_fetch',
    ]);
});

it('maps every provider tool the registry advertises for Anthropic', function () {
    $mapper = new ToolMapper;

    // Drift guard: anything the registry says Anthropic supports must actually map.
    foreach (ProviderToolRegistry::forProvider('anthropic') as $type) {
        $tool = new class($type) extends ProviderTool
        {
            public function __construct(private string $t)
            {
                parent::__construct();
            }

            public function type(): string
            {
                return $this->t;
            }
        };

        $result = $mapper->mapProviderTools([$tool]);

        expect($result)->toHaveCount(1);
        expect($result[0]['name'])->not->toBeEmpty();
    }
});

it('parses empty tool calls returns empty', function () {
    $mapper = new ToolMapper;

    $result = $mapper->parseToolCalls([]);

    expect($result)->toBe([]);
});

it('logs a warning and drops provider tools Anthropic does not support', function () {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message, array $context) {
            return str_contains($message, 'not supported')
                && $context['provider'] === 'anthropic'
                && $context['tools'] === ['code_interpreter'];
        });

    $mapper = new ToolMapper;
    // web_search is supported (kept); code_interpreter is not (dropped + warned).
    $result = $mapper->mapProviderTools([new WebSearch, new CodeInterpreter]);

    expect($result)->toHaveCount(1);
    expect($result[0]['type'])->toBe('web_search_20250305');
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
