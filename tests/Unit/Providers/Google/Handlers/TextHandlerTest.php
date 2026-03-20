<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Google\Handlers\Text;
use Atlasphp\Atlas\Providers\Google\MediaResolver;
use Atlasphp\Atlas\Providers\Google\MessageFactory;
use Atlasphp\Atlas\Providers\Google\ResponseParser;
use Atlasphp\Atlas\Providers\Google\ToolMapper;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Tools\ToolDefinition;
use Illuminate\Support\Facades\Http;

function makeGoogleTextHandler(): Text
{
    $toolMapper = new ToolMapper;

    return new Text(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://generativelanguage.googleapis.com']),
        http: app(HttpClient::class),
        messages: new MessageFactory,
        media: new MediaResolver,
        tools: $toolMapper,
        parser: new ResponseParser($toolMapper),
    );
}

function makeGoogleTextRequest(array $overrides = []): TextRequest
{
    return new TextRequest(
        model: $overrides['model'] ?? 'gemini-2.5-flash',
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

function fakeGeminiTextResponse(array $overrides = []): array
{
    return [
        'candidates' => [
            [
                'content' => ['parts' => [['text' => $overrides['text'] ?? 'Hello!']], 'role' => 'model'],
                'finishReason' => $overrides['finishReason'] ?? 'STOP',
            ],
        ],
        'usageMetadata' => [
            'promptTokenCount' => $overrides['promptTokenCount'] ?? 10,
            'candidatesTokenCount' => $overrides['candidatesTokenCount'] ?? 5,
        ],
    ];
}

it('sends text request to generateContent endpoint', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(fakeGeminiTextResponse()),
    ]);

    $handler = makeGoogleTextHandler();
    $response = $handler->text(makeGoogleTextRequest());

    expect($response)->toBeInstanceOf(TextResponse::class);
    expect($response->text)->toBe('Hello!');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/v1beta/models/gemini-2.5-flash:generateContent')
            && isset($request['contents']);
    });
});

it('uses x-goog-api-key header', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(fakeGeminiTextResponse()),
    ]);

    $handler = makeGoogleTextHandler();
    $handler->text(makeGoogleTextRequest());

    Http::assertSent(function ($request) {
        return $request->hasHeader('x-goog-api-key', 'test-key')
            && ! $request->hasHeader('Authorization');
    });
});

it('includes system_instruction when instructions provided', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(fakeGeminiTextResponse()),
    ]);

    $handler = makeGoogleTextHandler();
    $handler->text(makeGoogleTextRequest(['instructions' => 'Be concise']));

    Http::assertSent(function ($request) {
        return isset($request['system_instruction'])
            && $request['system_instruction']['parts'][0]['text'] === 'Be concise';
    });
});

it('includes generationConfig with maxOutputTokens and temperature', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(fakeGeminiTextResponse()),
    ]);

    $handler = makeGoogleTextHandler();
    $handler->text(makeGoogleTextRequest(['maxTokens' => 200, 'temperature' => 0.7]));

    Http::assertSent(function ($request) {
        return $request['generationConfig']['maxOutputTokens'] === 200
            && $request['generationConfig']['temperature'] === 0.7;
    });
});

it('sets responseMimeType and responseSchema for structured output', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(fakeGeminiTextResponse(['text' => '{"name":"John"}'])),
    ]);

    $schema = new Schema('person', 'A person', ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]]);

    $handler = makeGoogleTextHandler();
    $response = $handler->structured(makeGoogleTextRequest(['schema' => $schema]));

    expect($response)->toBeInstanceOf(StructuredResponse::class);
    expect($response->structured)->toBe(['name' => 'John']);

    Http::assertSent(function ($request) {
        return $request['generationConfig']['responseMimeType'] === 'application/json'
            && isset($request['generationConfig']['responseSchema']);
    });
});

it('stream hits streamGenerateContent endpoint with alt=sse', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response('data: '.json_encode(fakeGeminiTextResponse())."\n\n"),
    ]);

    $handler = makeGoogleTextHandler();

    Http::assertSentCount(0);

    // We can't fully test streaming without a real PSR-7 stream,
    // but we verify the URL is correct by checking the sent request
    try {
        $handler->stream(makeGoogleTextRequest());
    } catch (Throwable) {
        // Stream parsing may fail with fake response, that's ok
    }

    Http::assertSent(function ($request) {
        return str_contains($request->url(), ':streamGenerateContent')
            && str_contains($request->url(), 'alt=sse');
    });
});

it('wraps tools in function_declarations', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(fakeGeminiTextResponse()),
    ]);

    $handler = makeGoogleTextHandler();
    $handler->text(makeGoogleTextRequest([
        'tools' => [new ToolDefinition('search', 'Search', ['type' => 'object', 'properties' => ['q' => ['type' => 'string']]])],
    ]));

    Http::assertSent(function ($request) {
        $tools = $request['tools'] ?? [];

        return isset($tools[0]['function_declarations'])
            && $tools[0]['function_declarations'][0]['name'] === 'search';
    });
});
