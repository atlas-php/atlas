<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Anthropic\Handlers\Text;
use Atlasphp\Atlas\Providers\Anthropic\MediaResolver;
use Atlasphp\Atlas\Providers\Anthropic\MessageFactory;
use Atlasphp\Atlas\Providers\Anthropic\ResponseParser;
use Atlasphp\Atlas\Providers\Anthropic\ToolMapper;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
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
