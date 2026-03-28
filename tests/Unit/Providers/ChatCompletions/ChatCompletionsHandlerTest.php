<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasCache;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Providers\ChatCompletions\ChatCompletionsDriver;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\RerankRequest;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Schema\Schema;
use Illuminate\Support\Facades\Http;

function makeChatCompletionsDriver(): ChatCompletionsDriver
{
    return new ChatCompletionsDriver(
        config: new ProviderConfig(
            apiKey: 'test-key',
            baseUrl: 'http://localhost:11434/v1',
        ),
        http: app(HttpClient::class),
        cache: app(AtlasCache::class),
    );
}

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
