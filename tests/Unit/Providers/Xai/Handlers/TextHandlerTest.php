<?php

declare(strict_types=1);

use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Providers\OpenAi\MediaResolver;
use Atlasphp\Atlas\Providers\OpenAi\ResponseParser;
use Atlasphp\Atlas\Providers\OpenAi\ToolMapper;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\Tools\WebSearch;
use Atlasphp\Atlas\Providers\Xai\Handlers\Text;
use Atlasphp\Atlas\Providers\Xai\MessageFactory;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Tools\ToolDefinition;
use Illuminate\Support\Facades\Http;

function makeXaiTextHandler(?HttpClient $http = null): Text
{
    $toolMapper = new ToolMapper;

    return new Text(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.x.ai/v1']),
        http: $http ?? app(HttpClient::class),
        messages: new MessageFactory,
        media: new MediaResolver,
        toolMapper: $toolMapper,
        parser: new ResponseParser($toolMapper),
    );
}

function makeXaiHandlerTextRequest(array $overrides = []): TextRequest
{
    return new TextRequest(
        model: $overrides['model'] ?? 'grok-3',
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

it('does not include instructions in body', function () {
    Http::fake([
        'api.x.ai/v1/responses' => Http::response([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Hi']]]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ]),
    ]);

    $handler = makeXaiTextHandler();
    $response = $handler->text(makeXaiHandlerTextRequest(['instructions' => 'Be concise']));

    expect($response)->toBeInstanceOf(TextResponse::class);
    expect($response->text)->toBe('Hi');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.x.ai/v1/responses'
            && ! array_key_exists('instructions', $request->data())
            && $request['store'] === false;
    });
});

it('puts instructions as system message in input', function () {
    Http::fake([
        'api.x.ai/v1/responses' => Http::response([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'ok']]]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ]),
    ]);

    $handler = makeXaiTextHandler();
    $handler->text(makeXaiHandlerTextRequest(['instructions' => 'Be concise']));

    Http::assertSent(function ($request) {
        $input = $request['input'];

        return $input[0]['role'] === 'system'
            && $input[0]['content'] === 'Be concise';
    });
});

it('defaults store to false', function () {
    Http::fake([
        'api.x.ai/v1/responses' => Http::response([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'ok']]]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ]),
    ]);

    $handler = makeXaiTextHandler();
    $handler->text(makeXaiHandlerTextRequest());

    Http::assertSent(function ($request) {
        return $request['store'] === false;
    });
});

it('passes tools through', function () {
    Http::fake([
        'api.x.ai/v1/responses' => Http::response([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'ok']]]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ]),
    ]);

    $handler = makeXaiTextHandler();
    $handler->text(makeXaiHandlerTextRequest([
        'tools' => [new ToolDefinition('search', 'Search', ['type' => 'object'])],
        'providerTools' => [new WebSearch],
    ]));

    Http::assertSent(function ($request) {
        $tools = $request['tools'];

        return count($tools) === 2
            && $tools[0]['type'] === 'function'
            && $tools[0]['name'] === 'search'
            && $tools[1]['type'] === 'web_search';
    });
});

it('handles structured output', function () {
    Http::fake([
        'api.x.ai/v1/responses' => Http::response([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => '{"name":"John"}']]]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ]),
    ]);

    $schema = new Schema('person', 'A person', ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]]);

    $handler = makeXaiTextHandler();
    $response = $handler->structured(makeXaiHandlerTextRequest(['schema' => $schema]));

    expect($response)->toBeInstanceOf(StructuredResponse::class);
    expect($response->structured)->toBe(['name' => 'John']);

    Http::assertSent(function ($request) {
        return isset($request['text']['format'])
            && $request['text']['format']['type'] === 'json_schema'
            && ! array_key_exists('instructions', $request->data());
    });
});

it('passes provider options through', function () {
    Http::fake([
        'api.x.ai/v1/responses' => Http::response([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'ok']]]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ]),
    ]);

    $handler = makeXaiTextHandler();
    $handler->text(makeXaiHandlerTextRequest(['providerOptions' => ['reasoning_effort' => 'high']]));

    Http::assertSent(function ($request) {
        return $request['reasoning_effort'] === 'high';
    });
});
