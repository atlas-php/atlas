<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Providers\Anthropic\Handlers\Text;
use Atlasphp\Atlas\Providers\Anthropic\MediaResolver;
use Atlasphp\Atlas\Providers\Anthropic\MessageFactory;
use Atlasphp\Atlas\Providers\Anthropic\ResponseParser;
use Atlasphp\Atlas\Providers\Anthropic\ToolMapper;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\Tools\WebSearch;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Tools\ToolDefinition;
use Illuminate\Support\Facades\Http;

function makeAnthropicTextHandler(): Text
{
    $toolMapper = new ToolMapper;

    return new Text(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.anthropic.com/v1']),
        http: app(HttpClient::class),
        messages: new MessageFactory,
        media: new MediaResolver,
        tools: $toolMapper,
        parser: new ResponseParser($toolMapper),
    );
}

function makeAnthropicTextRequest(array $overrides = []): TextRequest
{
    return new TextRequest(
        model: $overrides['model'] ?? 'claude-sonnet-4-5-20250514',
        instructions: $overrides['instructions'] ?? null,
        message: $overrides['message'] ?? 'Hello',
        messageMedia: $overrides['messageMedia'] ?? [],
        messages: $overrides['messages'] ?? [],
        maxTokens: $overrides['maxTokens'] ?? null,
        temperature: $overrides['temperature'] ?? null,
        schema: $overrides['schema'] ?? null,
        tools: $overrides['tools'] ?? [],
        providerTools: $overrides['providerTools'] ?? [],
        providerOptions: $overrides['providerOptions'] ?? [],
    );
}

function fakeAnthropicTextResponse(array $overrides = []): array
{
    return [
        'id' => $overrides['id'] ?? 'msg_test123',
        'type' => 'message',
        'model' => $overrides['model'] ?? 'claude-sonnet-4-5-20250514',
        'content' => $overrides['content'] ?? [
            ['type' => 'text', 'text' => $overrides['text'] ?? 'Hello!'],
        ],
        'stop_reason' => $overrides['stop_reason'] ?? 'end_turn',
        'usage' => [
            'input_tokens' => $overrides['input_tokens'] ?? 10,
            'output_tokens' => $overrides['output_tokens'] ?? 5,
        ],
    ];
}

it('sends client and provider tools together in the tools array', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeAnthropicTextResponse()),
    ]);

    $handler = makeAnthropicTextHandler();
    $handler->text(makeAnthropicTextRequest([
        'tools' => [new ToolDefinition('search', 'Search', ['type' => 'object'])],
        'providerTools' => [new WebSearch(allowedDomains: ['laravel.com'])],
    ]));

    Http::assertSent(function ($request) {
        $tools = $request['tools'];

        return count($tools) === 2
            && $tools[0]['name'] === 'search'
            && $tools[1]['type'] === 'web_search_20250305'
            && $tools[1]['name'] === 'web_search'
            && $tools[1]['allowed_domains'] === ['laravel.com'];
    });
});

it('sends text request to messages endpoint', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeAnthropicTextResponse()),
    ]);

    $handler = makeAnthropicTextHandler();
    $response = $handler->text(makeAnthropicTextRequest());

    expect($response)->toBeInstanceOf(TextResponse::class);
    expect($response->text)->toBe('Hello!');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/messages')
            && isset($request['messages']);
    });
});

it('uses x-api-key header not Authorization Bearer', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeAnthropicTextResponse()),
    ]);

    $handler = makeAnthropicTextHandler();
    $handler->text(makeAnthropicTextRequest());

    Http::assertSent(function ($request) {
        return $request->hasHeader('x-api-key', 'test-key')
            && ! $request->hasHeader('Authorization');
    });
});

it('includes anthropic-version header', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeAnthropicTextResponse()),
    ]);

    $handler = makeAnthropicTextHandler();
    $handler->text(makeAnthropicTextRequest());

    Http::assertSent(function ($request) {
        return $request->hasHeader('anthropic-version', '2023-06-01');
    });
});

it('includes system as top-level param when instructions provided', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeAnthropicTextResponse()),
    ]);

    $handler = makeAnthropicTextHandler();
    $handler->text(makeAnthropicTextRequest(['instructions' => 'Be concise']));

    Http::assertSent(function ($request) {
        return isset($request['system'])
            && $request['system'] === 'Be concise';
    });
});

it('includes max_tokens with default 4096', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeAnthropicTextResponse()),
    ]);

    $handler = makeAnthropicTextHandler();
    $handler->text(makeAnthropicTextRequest());

    Http::assertSent(function ($request) {
        return $request['max_tokens'] === 4096;
    });
});

it('includes explicit max_tokens when provided', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeAnthropicTextResponse()),
    ]);

    $handler = makeAnthropicTextHandler();
    $handler->text(makeAnthropicTextRequest(['maxTokens' => 200]));

    Http::assertSent(function ($request) {
        return $request['max_tokens'] === 200;
    });
});

it('structured output uses tool_choice forced tool', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeAnthropicTextResponse([
            'content' => [
                ['type' => 'tool_use', 'id' => 'toolu_123', 'name' => 'person', 'input' => ['name' => 'John', 'age' => 30]],
            ],
            'stop_reason' => 'tool_use',
        ])),
    ]);

    $schema = new Schema('person', 'A person', ['type' => 'object', 'properties' => ['name' => ['type' => 'string'], 'age' => ['type' => 'integer']]]);

    $handler = makeAnthropicTextHandler();
    $response = $handler->structured(makeAnthropicTextRequest(['schema' => $schema]));

    expect($response)->toBeInstanceOf(StructuredResponse::class);
    expect($response->structured)->toBe(['name' => 'John', 'age' => 30]);

    Http::assertSent(function ($request) {
        $tools = $request['tools'] ?? [];
        $toolChoice = $request['tool_choice'] ?? [];

        return isset($tools[0]['name']) && $tools[0]['name'] === 'person'
            && $toolChoice['type'] === 'tool'
            && $toolChoice['name'] === 'person';
    });
});

it('stream hits same endpoint with stream true', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response("event: message_start\ndata: {}\n\n"),
    ]);

    $handler = makeAnthropicTextHandler();

    Http::assertSentCount(0);

    try {
        $handler->stream(makeAnthropicTextRequest());
    } catch (Throwable) {
        // Stream parsing may fail with fake response
    }

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/messages')
            && $request['stream'] === true;
    });
});

it('wraps tools in Anthropic format with input_schema', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeAnthropicTextResponse()),
    ]);

    $handler = makeAnthropicTextHandler();
    $handler->text(makeAnthropicTextRequest([
        'tools' => [new ToolDefinition('search', 'Search', ['type' => 'object', 'properties' => ['q' => ['type' => 'string']]])],
    ]));

    Http::assertSent(function ($request) {
        $tools = $request['tools'] ?? [];

        return isset($tools[0]['input_schema'])
            && $tools[0]['name'] === 'search';
    });
});

it('surfaces cache read/write tokens from the streamed message_start usage', function () {
    $sse = "event: message_start\n"
        ."data: {\"message\":{\"usage\":{\"input_tokens\":1000,\"cache_read_input_tokens\":900,\"cache_creation_input_tokens\":50}}}\n\n"
        ."event: message_delta\n"
        ."data: {\"delta\":{\"stop_reason\":\"end_turn\"},\"usage\":{\"output_tokens\":20}}\n\n"
        ."event: message_stop\ndata: {}\n\n";

    Http::fake([
        'api.anthropic.com/*' => Http::response($sse),
    ]);

    $handler = makeAnthropicTextHandler();
    $chunks = iterator_to_array($handler->stream(makeAnthropicTextRequest()));

    $done = array_values(array_filter(
        $chunks,
        fn ($c) => $c->type === ChunkType::Done && $c->usage !== null,
    ));

    expect($done)->toHaveCount(1)
        ->and($done[0]->usage->inputTokens)->toBe(1000)
        ->and($done[0]->usage->outputTokens)->toBe(20)
        ->and($done[0]->usage->cachedTokens)->toBe(900)
        ->and($done[0]->usage->cacheWriteTokens)->toBe(50);
});

it('leaves stream cache tokens null when message_start omits cache usage', function () {
    $sse = "event: message_start\n"
        ."data: {\"message\":{\"usage\":{\"input_tokens\":12}}}\n\n"
        ."event: message_delta\n"
        ."data: {\"delta\":{\"stop_reason\":\"end_turn\"},\"usage\":{\"output_tokens\":3}}\n\n"
        ."event: message_stop\ndata: {}\n\n";

    Http::fake([
        'api.anthropic.com/*' => Http::response($sse),
    ]);

    $chunks = iterator_to_array(makeAnthropicTextHandler()->stream(makeAnthropicTextRequest()));

    $done = array_values(array_filter(
        $chunks,
        fn ($c) => $c->type === ChunkType::Done && $c->usage !== null,
    ));

    expect($done)->toHaveCount(1)
        ->and($done[0]->usage->inputTokens)->toBe(12)
        ->and($done[0]->usage->cachedTokens)->toBeNull()
        ->and($done[0]->usage->cacheWriteTokens)->toBeNull();
});

it('emits a ToolCall chunk from a streamed tool_use content block', function () {
    $sse = "event: message_start\n"
        ."data: {\"message\":{\"usage\":{\"input_tokens\":10}}}\n\n"
        ."event: content_block_start\n"
        ."data: {\"index\":0,\"content_block\":{\"type\":\"tool_use\",\"id\":\"toolu_abc\",\"name\":\"search\"}}\n\n"
        ."event: content_block_delta\n"
        ."data: {\"index\":0,\"delta\":{\"type\":\"input_json_delta\",\"partial_json\":\"{\\\"q\\\":\"}}\n\n"
        ."event: content_block_delta\n"
        ."data: {\"index\":0,\"delta\":{\"type\":\"input_json_delta\",\"partial_json\":\"\\\"cats\\\"}\"}}\n\n"
        ."event: content_block_stop\ndata: {\"index\":0}\n\n"
        ."event: message_delta\n"
        ."data: {\"delta\":{\"stop_reason\":\"tool_use\"},\"usage\":{\"output_tokens\":5}}\n\n"
        ."event: message_stop\ndata: {}\n\n";

    Http::fake([
        'api.anthropic.com/*' => Http::response($sse),
    ]);

    $chunks = iterator_to_array(makeAnthropicTextHandler()->stream(makeAnthropicTextRequest()));

    $toolChunks = array_values(array_filter(
        $chunks,
        fn ($c) => $c->type === ChunkType::ToolCall,
    ));

    expect($toolChunks)->toHaveCount(1)
        ->and($toolChunks[0]->toolCalls[0]->id)->toBe('toolu_abc')
        ->and($toolChunks[0]->toolCalls[0]->name)->toBe('search')
        ->and($toolChunks[0]->toolCalls[0]->arguments)->toBe(['q' => 'cats']);
});
