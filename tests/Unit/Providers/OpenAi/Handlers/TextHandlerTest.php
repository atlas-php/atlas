<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\OpenAi\Handlers\Text;
use Atlasphp\Atlas\Providers\OpenAi\MediaResolver;
use Atlasphp\Atlas\Providers\OpenAi\MessageFactory;
use Atlasphp\Atlas\Providers\OpenAi\ResponseParser;
use Atlasphp\Atlas\Providers\OpenAi\ToolMapper;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\Tools\WebSearch;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Tools\ToolDefinition;
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
    );
}

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
