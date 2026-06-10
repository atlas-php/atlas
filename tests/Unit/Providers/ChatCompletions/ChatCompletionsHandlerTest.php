<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasCache;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Exceptions\ProviderException;
use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Providers\ChatCompletions\ChatCompletionsDriver;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\RequestConfig;
use Atlasphp\Atlas\Requests\RerankRequest;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Tools\ToolChoice;
use Atlasphp\Atlas\Tools\ToolDefinition;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

function makeChatCompletionsDriver(?HttpClient $http = null): ChatCompletionsDriver
{
    return new ChatCompletionsDriver(
        config: new ProviderConfig(
            apiKey: 'test-key',
            baseUrl: 'http://localhost:11434/v1',
        ),
        http: $http ?? app(HttpClient::class),
        cache: app(AtlasCache::class),
    );
}

it('a mid-stream error while streaming throws a ProviderException carrying the model', function () {
    Http::fake([
        'localhost:11434/v1/chat/completions' => Http::response(
            "data: {\"error\":{\"message\":\"model not found\"}}\n\n",
            200,
        ),
    ]);

    $request = new TextRequest(
        model: 'llama3.2', instructions: null, message: 'Hi', messageMedia: [], messages: [],
        maxTokens: null, temperature: null, schema: null, tools: [], providerTools: [], providerOptions: [],
    );

    $caught = null;

    try {
        foreach (makeChatCompletionsDriver()->stream($request) as $chunk) {
            // consume the stream
        }
    } catch (ProviderException $e) {
        $caught = $e;
    }

    expect($caught)->not->toBeNull();
    expect($caught->model)->toBe('llama3.2');
    expect($caught->providerMessage)->toBe('model not found');
});

it('forwards the request config to the HTTP layer (post and stream)', function () {
    $config = (new RequestConfig(30, 5, 2))->withoutRetry();

    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('post')->once()
        ->withArgs(fn (string $url, array $headers, array $body, int $timeout, ?RequestConfig $cfg) => $cfg === $config)
        ->andReturn(['choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1]]);
    $http->shouldReceive('stream')->once()
        ->withArgs(fn (string $url, array $headers, array $body, int $timeout, ?RequestConfig $cfg) => $cfg === $config)
        ->andReturn(Mockery::mock(Response::class));

    $driver = makeChatCompletionsDriver($http);
    $request = new TextRequest(
        model: 'llama3.1', instructions: null, message: 'Hi', messageMedia: [], messages: [],
        maxTokens: null, temperature: null, schema: null, tools: [], providerTools: [],
        providerOptions: [], requestConfig: $config,
    );

    $driver->text($request);
    $driver->stream($request);
});

// ─── Provider Handler ───────────────────────────────────────────────────────

it('lists models via provider handler', function () {
    Http::fake([
        'localhost:11434/v1/models' => Http::response([
            'data' => [
                ['id' => 'llama3.1', 'object' => 'model'],
                ['id' => 'mistral', 'object' => 'model'],
            ],
        ]),
    ]);

    $models = makeChatCompletionsDriver()->models();

    expect($models->models)->toContain('llama3.1');
    expect($models->models)->toContain('mistral');
});

it('validates via provider handler', function () {
    Http::fake([
        'localhost:11434/v1/models' => Http::response([
            'data' => [['id' => 'llama3.1']],
        ]),
    ]);

    expect(makeChatCompletionsDriver()->validate())->toBeTrue();
});

it('voices returns empty list', function () {
    expect(makeChatCompletionsDriver()->voices()->voices)->toBe([]);
});

// ─── Text Handler ───────────────────────────────────────────────────────────

it('sends text request to chat/completions endpoint', function () {
    Http::fake([
        'localhost:11434/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => 'Hello!', 'role' => 'assistant'], 'finish_reason' => 'stop'],
            ],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
        ]),
    ]);

    $request = new TextRequest(
        model: 'llama3.1',
        instructions: null,
        message: 'Hi',
        messageMedia: [],
        messages: [],
        maxTokens: null,
        temperature: null,
        schema: null,
        tools: [],
        providerTools: [],
        providerOptions: [],
    );

    $response = makeChatCompletionsDriver()->text($request);

    expect($response->text)->toBe('Hello!');
    expect($response->finishReason)->toBe(FinishReason::Stop);
    expect($response->usage->inputTokens)->toBe(10);
    expect($response->usage->outputTokens)->toBe(5);

    Http::assertSent(fn ($r) => str_contains($r->url(), '/chat/completions'));
});

it('emits tool_choice when a choice is set with tools', function () {
    Http::fake([
        'localhost:11434/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'ok', 'role' => 'assistant'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
        ]),
    ]);

    $request = new TextRequest(
        model: 'llama3.1',
        instructions: null,
        message: 'Hi',
        messageMedia: [],
        messages: [],
        maxTokens: null,
        temperature: null,
        schema: null,
        tools: [new ToolDefinition('get_weather', 'Get weather', ['type' => 'object'])],
        providerTools: [],
        providerOptions: [],
        toolChoice: ToolChoice::required(),
    );

    makeChatCompletionsDriver()->text($request);

    Http::assertSent(fn ($r) => ($r->data()['tool_choice'] ?? null) === 'required');
});

it('does not emit tool_choice during structured output', function () {
    Http::fake([
        'localhost:11434/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => '{}', 'role' => 'assistant'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
        ]),
    ]);

    $request = new TextRequest(
        model: 'llama3.1',
        instructions: null,
        message: 'Give me data',
        messageMedia: [],
        messages: [],
        maxTokens: null,
        temperature: null,
        schema: new Schema('out', 'Out', ['type' => 'object', 'properties' => ['x' => ['type' => 'string']]]),
        tools: [new ToolDefinition('get_weather', 'Get weather', ['type' => 'object'])],
        providerTools: [],
        providerOptions: [],
        toolChoice: ToolChoice::required(),
    );

    makeChatCompletionsDriver()->structured($request);

    Http::assertSent(fn ($r) => ! isset($r->data()['tool_choice']));
});

it('sends structured request with json_schema response_format', function () {
    Http::fake([
        'localhost:11434/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => '{"name":"test"}', 'role' => 'assistant'], 'finish_reason' => 'stop'],
            ],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
        ]),
    ]);

    $schema = new Schema('output', 'Output data', [
        'type' => 'object',
        'properties' => ['name' => ['type' => 'string']],
    ]);

    $request = new TextRequest(
        model: 'llama3.1',
        instructions: null,
        message: 'Give me data',
        messageMedia: [],
        messages: [],
        maxTokens: null,
        temperature: null,
        schema: $schema,
        tools: [],
        providerTools: [],
        providerOptions: [],
    );

    $response = makeChatCompletionsDriver()->structured($request);

    expect($response->structured)->toBe(['name' => 'test']);

    Http::assertSent(function ($r) {
        $body = $r->data();

        return isset($body['response_format']['type'])
            && $body['response_format']['type'] === 'json_schema';
    });
});

it('normalizes a builder schema to strict mode in response_format', function () {
    Http::fake([
        'localhost:11434/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => '{"name":"test","phone":null}', 'role' => 'assistant'], 'finish_reason' => 'stop'],
            ],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
        ]),
    ]);

    $schema = Schema::object('output', 'Output data')
        ->string('name', 'Name')
        ->string('phone', 'Phone')->optional()
        ->build();

    $request = new TextRequest(
        model: 'llama3.1',
        instructions: null,
        message: 'Give me data',
        messageMedia: [],
        messages: [],
        maxTokens: null,
        temperature: null,
        schema: $schema,
        tools: [],
        providerTools: [],
        providerOptions: [],
    );

    makeChatCompletionsDriver()->structured($request);

    Http::assertSent(function ($r) {
        $sent = $r->data()['response_format']['json_schema']['schema'];

        return $sent['additionalProperties'] === false
            && $sent['required'] === ['name', 'phone']
            && $sent['properties']['phone']['type'] === ['string', 'null'];
    });
});

it('omits Authorization header when api key is empty', function () {
    $driver = new ChatCompletionsDriver(
        config: new ProviderConfig(
            apiKey: '',
            baseUrl: 'http://localhost:11434/v1',
        ),
        http: app(HttpClient::class),
    );

    Http::fake([
        'localhost:11434/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => 'ok', 'role' => 'assistant'], 'finish_reason' => 'stop'],
            ],
            'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
        ]),
    ]);

    $request = new TextRequest(
        model: 'llama3.1',
        instructions: null,
        message: 'Hi',
        messageMedia: [],
        messages: [],
        maxTokens: null,
        temperature: null,
        schema: null,
        tools: [],
        providerTools: [],
        providerOptions: [],
    );

    $driver->text($request);

    Http::assertSent(fn ($r) => ! $r->hasHeader('Authorization'));
});

// ─── Unsupported modalities ─────────────────────────────────────────────────

it('throws UnsupportedFeatureException for rerank', function () {
    makeChatCompletionsDriver()->rerank(new RerankRequest('model', 'query', ['doc']));
})->throws(UnsupportedFeatureException::class, 'rerank');
