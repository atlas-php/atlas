<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\ReasoningEffort;
use Atlasphp\Atlas\Exceptions\ProviderException;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Providers\Anthropic\Handlers\Text;
use Atlasphp\Atlas\Providers\Anthropic\MediaResolver;
use Atlasphp\Atlas\Providers\Anthropic\MessageFactory;
use Atlasphp\Atlas\Providers\Anthropic\ResponseParser;
use Atlasphp\Atlas\Providers\Anthropic\ToolMapper;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\Tools\WebSearch;
use Atlasphp\Atlas\RequestConfig;
use Atlasphp\Atlas\Requests\Reasoning;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Tools\ToolChoice;
use Atlasphp\Atlas\Tools\ToolDefinition;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

function makeAnthropicTextHandler(?HttpClient $http = null): Text
{
    $toolMapper = new ToolMapper;

    return new Text(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.anthropic.com/v1']),
        http: $http ?? app(HttpClient::class),
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
        toolChoice: $overrides['toolChoice'] ?? null,
        requestConfig: $overrides['requestConfig'] ?? null,
        reasoning: $overrides['reasoning'] ?? null,
    );
}

it('a mid-stream error while streaming throws a ProviderException carrying the model', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(
            "event: error\ndata: {\"type\":\"error\",\"error\":{\"message\":\"Overloaded\"}}\n\n",
            200,
        ),
    ]);

    $caught = null;

    try {
        foreach (makeAnthropicTextHandler()->stream(makeAnthropicTextRequest(['model' => 'claude-sonnet-4-5'])) as $chunk) {
            // consume the stream
        }
    } catch (ProviderException $e) {
        $caught = $e;
    }

    expect($caught)->not->toBeNull();
    expect($caught->model)->toBe('claude-sonnet-4-5');
    expect($caught->providerMessage)->toBe('Overloaded');
});

it('forwards the request config to the HTTP layer (post and stream)', function () {
    $config = (new RequestConfig(30, 5, 2))->withoutRetry();

    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('post')->once()
        ->withArgs(fn (string $url, array $headers, array $body, int $timeout, ?RequestConfig $cfg) => $cfg === $config)
        ->andReturn(['content' => [['type' => 'text', 'text' => 'ok']], 'usage' => ['input_tokens' => 1, 'output_tokens' => 1], 'stop_reason' => 'end_turn']);
    $http->shouldReceive('stream')->once()
        ->withArgs(fn (string $url, array $headers, array $body, int $timeout, ?RequestConfig $cfg) => $cfg === $config)
        ->andReturn(Mockery::mock(Response::class));

    $handler = makeAnthropicTextHandler($http);
    $handler->text(makeAnthropicTextRequest(['requestConfig' => $config]));
    $handler->stream(makeAnthropicTextRequest(['requestConfig' => $config]));
});

it('emits the Anthropic tool_choice object when a choice is set with tools', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(fakeAnthropicTextResponse())]);

    makeAnthropicTextHandler()->text(makeAnthropicTextRequest([
        'tools' => [new ToolDefinition('get_weather', 'Get weather', ['type' => 'object'])],
        'toolChoice' => ToolChoice::required(),
    ]));

    Http::assertSent(fn ($request) => $request['tool_choice'] === ['type' => 'any']);
});

it('does not emit a normalized tool_choice during structured output', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(fakeAnthropicTextResponse())]);

    makeAnthropicTextHandler()->structured(makeAnthropicTextRequest([
        'tools' => [new ToolDefinition('get_weather', 'Get weather', ['type' => 'object'])],
        'toolChoice' => ToolChoice::required(),
        'schema' => new Schema('out', 'Out', ['type' => 'object', 'properties' => (object) []]),
    ]));

    // structured() forces the schema tool, not the normalized "any".
    Http::assertSent(fn ($request) => $request['tool_choice'] === ['type' => 'tool', 'name' => 'out']);
});

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

it('emits a thinking block with the effort-derived budget when reasoning is set', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'ok']],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ]),
    ]);

    makeAnthropicTextHandler()->text(makeAnthropicTextRequest([
        'reasoning' => new Reasoning(ReasoningEffort::Medium),
    ]));

    Http::assertSent(fn ($request) => $request['thinking'] === ['type' => 'enabled', 'budget_tokens' => 8192]);
});

it('honors an explicit thinking budget over the effort default', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'ok']],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ]),
    ]);

    makeAnthropicTextHandler()->text(makeAnthropicTextRequest([
        'reasoning' => new Reasoning(ReasoningEffort::Low, budgetTokens: 12000),
    ]));

    Http::assertSent(fn ($request) => $request['thinking']['budget_tokens'] === 12000);
});

it('bumps max_tokens above the thinking budget and drops temperature', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'ok']],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ]),
    ]);

    // Default max_tokens (4096) is below an 8192 budget — must be bumped, and the
    // explicit temperature must be removed (Anthropic rejects it with thinking on).
    makeAnthropicTextHandler()->text(makeAnthropicTextRequest([
        'temperature' => 0.7,
        'reasoning' => new Reasoning(ReasoningEffort::Medium),
    ]));

    Http::assertSent(fn ($request) => $request['max_tokens'] === 8192 + 4096 && ! isset($request['temperature']));
});

it('omits the thinking block when reasoning is not set', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'ok']],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ]),
    ]);

    makeAnthropicTextHandler()->text(makeAnthropicTextRequest());

    Http::assertSent(fn ($request) => ! isset($request['thinking']));
});
