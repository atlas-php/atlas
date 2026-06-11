<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\ReasoningEffort;
use Atlasphp\Atlas\Exceptions\ProviderException;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Providers\OpenAi\Handlers\Text;
use Atlasphp\Atlas\Providers\OpenAi\MediaResolver;
use Atlasphp\Atlas\Providers\OpenAi\MessageFactory;
use Atlasphp\Atlas\Providers\OpenAi\ResponseParser;
use Atlasphp\Atlas\Providers\OpenAi\ToolMapper;
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

function makeTextHandler(?HttpClient $http = null): Text
{
    $toolMapper = new ToolMapper;

    return new Text(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.openai.com/v1']),
        http: $http ?? app(HttpClient::class),
        messages: new MessageFactory,
        media: new MediaResolver,
        toolMapper: $toolMapper,
        parser: new ResponseParser($toolMapper),
    );
}

function makeOpenAiTextRequest(array $overrides = []): TextRequest
{
    return new TextRequest(
        model: $overrides['model'] ?? 'gpt-4o',
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

it('emits tool_choice when a choice is set alongside tools', function () {
    Http::fake([
        'api.openai.com/v1/responses' => Http::response([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'ok']]]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ]),
    ]);

    makeTextHandler()->text(makeOpenAiTextRequest([
        'tools' => [new ToolDefinition('get_weather', 'Get weather', [])],
        'toolChoice' => ToolChoice::required(),
    ]));

    Http::assertSent(fn ($request) => isset($request['tools']) && $request['tool_choice'] === 'required');
});

it('omits the normalized tool_choice when there are no tools', function () {
    Http::fake([
        'api.openai.com/v1/responses' => Http::response([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'ok']]]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ]),
    ]);

    makeTextHandler()->text(makeOpenAiTextRequest([
        'toolChoice' => ToolChoice::required(),
    ]));

    Http::assertSent(fn ($request) => ! isset($request['tool_choice']));
});

it('does not emit a normalized tool_choice during structured output', function () {
    Http::fake([
        'api.openai.com/v1/responses' => Http::response([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => '{}']]]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ]),
    ]);

    makeTextHandler()->structured(makeOpenAiTextRequest([
        'tools' => [new ToolDefinition('get_weather', 'Get weather', [])],
        'toolChoice' => ToolChoice::required(),
        'schema' => new Schema('out', 'Out', ['type' => 'object', 'properties' => (object) []]),
    ]));

    Http::assertSent(fn ($request) => ! isset($request['tool_choice']));
});

it('sends text request to /v1/responses', function () {
    Http::fake([
        'api.openai.com/v1/responses' => Http::response([
            'status' => 'completed',
            'output' => [
                ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Hi there']]],
            ],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ]),
    ]);

    $handler = makeTextHandler();
    $response = $handler->text(makeOpenAiTextRequest());

    expect($response)->toBeInstanceOf(TextResponse::class);
    expect($response->text)->toBe('Hi there');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.openai.com/v1/responses'
            && $request['model'] === 'gpt-4o'
            && $request['store'] === false
            && isset($request['input']);
    });
});

it('sets instructions as top-level param', function () {
    Http::fake([
        'api.openai.com/v1/responses' => Http::response([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'ok']]]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ]),
    ]);

    $handler = makeTextHandler();
    $handler->text(makeOpenAiTextRequest(['instructions' => 'Be concise']));

    Http::assertSent(function ($request) {
        return $request['instructions'] === 'Be concise';
    });
});

it('uses max_output_tokens not max_tokens', function () {
    Http::fake([
        'api.openai.com/v1/responses' => Http::response([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'ok']]]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ]),
    ]);

    $handler = makeTextHandler();
    $handler->text(makeOpenAiTextRequest(['maxTokens' => 100]));

    Http::assertSent(function ($request) {
        return $request['max_output_tokens'] === 100
            && ! isset($request['max_tokens']);
    });
});

it('merges function tools and provider tools', function () {
    Http::fake([
        'api.openai.com/v1/responses' => Http::response([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'ok']]]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ]),
    ]);

    $handler = makeTextHandler();
    $handler->text(makeOpenAiTextRequest([
        'tools' => [new ToolDefinition('search', 'Search', ['type' => 'object'])],
        'providerTools' => [new WebSearch],
    ]));

    Http::assertSent(function ($request) {
        $tools = $request['tools'];

        return count($tools) === 2
            && $tools[0]['type'] === 'function'
            && $tools[0]['name'] === 'search'
            && $tools[0]['strict'] === true
            && $tools[1]['type'] === 'web_search';
    });
});

it('sends structured request with text.format', function () {
    Http::fake([
        'api.openai.com/v1/responses' => Http::response([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => '{"name":"John"}']]]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ]),
    ]);

    $schema = new Schema('person', 'A person', ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]]);

    $handler = makeTextHandler();
    $response = $handler->structured(makeOpenAiTextRequest(['schema' => $schema]));

    expect($response)->toBeInstanceOf(StructuredResponse::class);
    expect($response->structured)->toBe(['name' => 'John']);

    Http::assertSent(function ($request) {
        return isset($request['text']['format'])
            && $request['text']['format']['type'] === 'json_schema'
            && $request['text']['format']['strict'] === true;
    });
});

it('normalizes a builder schema to OpenAI strict mode (additionalProperties + all required + nullable optionals)', function () {
    Http::fake([
        'api.openai.com/v1/responses' => Http::response([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => '{"name":"John","phone":null}']]]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ]),
    ]);

    $schema = Schema::object('person', 'A person')
        ->string('name', 'Name')
        ->string('phone', 'Phone')->optional()
        ->build();

    makeTextHandler()->structured(makeOpenAiTextRequest(['schema' => $schema]));

    Http::assertSent(function ($request) {
        $sent = $request['text']['format']['schema'];

        return $sent['additionalProperties'] === false
            && $sent['required'] === ['name', 'phone']
            && $sent['properties']['name']['type'] === 'string'
            && $sent['properties']['phone']['type'] === ['string', 'null'];
    });
});

it('a mid-stream error while streaming throws a ProviderException carrying the model', function () {
    Http::fake([
        'api.openai.com/v1/responses' => Http::response(
            "event: response.failed\ndata: {\"response\":{\"error\":{\"message\":\"boom\"}}}\n\n",
            200,
        ),
    ]);

    $caught = null;

    try {
        foreach (makeTextHandler()->stream(makeOpenAiTextRequest(['model' => 'gpt-4o'])) as $chunk) {
            // consume the stream
        }
    } catch (ProviderException $e) {
        $caught = $e;
    }

    expect($caught)->not->toBeNull();
    expect($caught->model)->toBe('gpt-4o');
    expect($caught->providerMessage)->toBe('boom');
});

it('forwards the request config to the HTTP layer when streaming', function () {
    $config = (new RequestConfig(30, 5, 2))->withTimeout(15);

    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('stream')->once()
        ->withArgs(fn (string $url, array $headers, array $body, int $timeout, ?RequestConfig $cfg) => $cfg === $config)
        ->andReturn(Mockery::mock(Response::class));

    makeTextHandler($http)->stream(makeOpenAiTextRequest(['requestConfig' => $config]));
});

it('forwards the request config to the HTTP layer for a text call', function () {
    $config = (new RequestConfig(30, 5, 2))->withoutRetry();

    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('post')->once()
        ->withArgs(fn (string $url, array $headers, array $body, int $timeout, ?RequestConfig $cfg) => $cfg === $config)
        ->andReturn([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'ok']]]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ]);

    makeTextHandler($http)->text(makeOpenAiTextRequest(['requestConfig' => $config]));
});

it('passes provider options through', function () {
    Http::fake([
        'api.openai.com/v1/responses' => Http::response([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'ok']]]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ]),
    ]);

    $handler = makeTextHandler();
    $handler->text(makeOpenAiTextRequest(['providerOptions' => ['reasoning_effort' => 'high']]));

    Http::assertSent(function ($request) {
        return $request['reasoning_effort'] === 'high';
    });
});

it('includes temperature in the payload when set', function () {
    Http::fake([
        'api.openai.com/v1/responses' => Http::response([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'ok']]]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ]),
    ]);

    makeTextHandler()->text(makeOpenAiTextRequest(['temperature' => 0.7]));

    Http::assertSent(fn ($request) => $request['temperature'] === 0.7);
});

it('omits temperature from the payload when null', function () {
    Http::fake([
        'api.openai.com/v1/responses' => Http::response([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'ok']]]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ]),
    ]);

    makeTextHandler()->text(makeOpenAiTextRequest(['temperature' => null]));

    Http::assertSent(fn ($request) => ! isset($request['temperature']));
});

it('maps reasoning effort to the Responses API reasoning object', function () {
    Http::fake([
        'api.openai.com/v1/responses' => Http::response([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'ok']]]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ]),
    ]);

    makeTextHandler()->text(makeOpenAiTextRequest(['reasoning' => new Reasoning(ReasoningEffort::High)]));

    Http::assertSent(fn ($request) => $request['reasoning']['effort'] === 'high' && ! isset($request['reasoning']['summary']));
});

it('requests a reasoning summary when includeSummary is set', function () {
    Http::fake([
        'api.openai.com/v1/responses' => Http::response([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'ok']]]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ]),
    ]);

    makeTextHandler()->text(makeOpenAiTextRequest([
        'reasoning' => new Reasoning(ReasoningEffort::Medium, includeSummary: true),
    ]));

    Http::assertSent(fn ($request) => $request['reasoning']['effort'] === 'medium' && $request['reasoning']['summary'] === 'auto');
});

it('omits reasoning from the payload when not set', function () {
    Http::fake([
        'api.openai.com/v1/responses' => Http::response([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'ok']]]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ]),
    ]);

    makeTextHandler()->text(makeOpenAiTextRequest());

    Http::assertSent(fn ($request) => ! isset($request['reasoning']) && ! isset($request['include']));
});

it('requests encrypted reasoning content for stateless replay when reasoning is set', function () {
    Http::fake([
        'api.openai.com/v1/responses' => Http::response([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'ok']]]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ]),
    ]);

    makeTextHandler()->text(makeOpenAiTextRequest(['reasoning' => new Reasoning(ReasoningEffort::High)]));

    Http::assertSent(fn ($request) => $request['include'] === ['reasoning.encrypted_content']);
});

it('counts tokens via the responses/input_tokens endpoint, stripping generation params', function () {
    Http::fake([
        'api.openai.com/v1/responses/input_tokens' => Http::response([
            'object' => 'response.input_tokens',
            'input_tokens' => 42,
        ]),
    ]);

    $count = makeTextHandler()->countTokens(makeOpenAiTextRequest([
        'maxTokens' => 256,
        'temperature' => 0.5,
    ]));

    expect($count->inputTokens)->toBe(42)
        ->and($count->estimated)->toBeFalse();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.openai.com/v1/responses/input_tokens'
            && ! isset($request['max_output_tokens'])
            && ! isset($request['temperature'])
            && ! isset($request['stream']);
    });
});

it('falls back to a heuristic estimate when input_tokens is unsupported', function () {
    Http::fake([
        'api.openai.com/v1/responses/input_tokens' => Http::response(['error' => 'not found'], 404),
    ]);

    $count = makeTextHandler()->countTokens(makeOpenAiTextRequest([
        'message' => 'Hello world, this is a heuristic fallback test.',
    ]));

    expect($count->estimated)->toBeTrue()
        ->and($count->inputTokens)->toBeGreaterThan(0);
});
