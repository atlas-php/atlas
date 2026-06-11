<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\AgentRegistry;
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Enums\ReasoningEffort;
use Atlasphp\Atlas\Enums\ToolChoiceMode;
use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Input\Audio;
use Atlasphp\Atlas\Input\Image;
use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Pending\AgentRequest;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;
use Atlasphp\Atlas\Testing\AtlasFake;
use Atlasphp\Atlas\Testing\StreamResponseFake;
use Atlasphp\Atlas\Testing\StructuredResponseFake;
use Atlasphp\Atlas\Testing\TextResponseFake;
use Atlasphp\Atlas\Tools\ToolChoice;
use Illuminate\Broadcasting\PrivateChannel;

function queuePayload(array $overrides = []): array
{
    return array_merge([
        'key' => 'queue-minimal',
        'message' => 'hi',
        'message_media' => [],
        'instructions' => null,
        'variables' => [],
        'meta' => [],
        'provider' => 'openai',
        'model' => 'gpt-4o',
        'max_tokens' => null,
        'temperature' => null,
        'max_steps' => null,
        'concurrent' => null,
        'cache' => null,
        'provider_options' => [],
        'middleware' => [],
        'owner_type' => null,
        'owner_id' => null,
        'message_owner_type' => null,
        'message_owner_id' => null,
        'conversation_id' => null,
        'message_limit' => null,
        'respond_mode' => false,
        'retry_mode' => false,
    ], $overrides);
}

// ─── Test agent ─────────────────────────────────────────────────────────────

class QueueTestMinimalAgent extends Agent
{
    public function key(): string
    {
        return 'queue-minimal';
    }
}

class QueueTestNoModelAgent extends Agent
{
    public function key(): string
    {
        return 'queue-no-model';
    }

    public function provider(): Provider|string|null
    {
        return Provider::OpenAI;
    }
}

// ─── Helpers ────────────────────────────────────────────────────────────────

function invokeRestoreMediaItem(array $item): ?Input
{
    $method = new ReflectionMethod(AgentRequest::class, 'restoreMediaItem');

    return $method->invoke(null, $item);
}

function invokeRestoreMedia(array $items): array
{
    $method = new ReflectionMethod(AgentRequest::class, 'restoreMedia');

    return $method->invoke(null, $items);
}

function mediaItemDefaults(array $overrides = []): array
{
    return array_merge([
        'class' => Image::class,
        'base64' => null,
        'mime' => null,
        'storage_path' => null,
        'storage_disk' => null,
        'url' => null,
        'path' => null,
        'file_id' => null,
    ], $overrides);
}

function registerQueueTestAgent(string $agentClass): void
{
    app(AgentRegistry::class)->register($agentClass);
}

// ─── restoreMediaItem ───────────────────────────────────────────────────────

it('restores media from base64', function () {
    $item = mediaItemDefaults([
        'class' => Image::class,
        'base64' => base64_encode('fake-image-data'),
        'mime' => 'image/png',
    ]);

    $result = invokeRestoreMediaItem($item);

    expect($result)
        ->toBeInstanceOf(Image::class)
        ->and($result->isBase64())->toBeTrue()
        ->and($result->data())->toBe(base64_encode('fake-image-data'))
        ->and($result->mimeType())->toBe('image/png');
});

it('restores media from URL', function () {
    $item = mediaItemDefaults([
        'class' => Image::class,
        'url' => 'https://example.com/photo.jpg',
    ]);

    $result = invokeRestoreMediaItem($item);

    expect($result)
        ->toBeInstanceOf(Image::class)
        ->and($result->isUrl())->toBeTrue()
        ->and($result->url())->toBe('https://example.com/photo.jpg');
});

it('restores media from file path', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'atlas_test_');
    file_put_contents($tmp, 'fake-image-bytes');

    $item = mediaItemDefaults([
        'class' => Image::class,
        'path' => $tmp,
    ]);

    $result = invokeRestoreMediaItem($item);

    expect($result)
        ->toBeInstanceOf(Image::class)
        ->and($result->isPath())->toBeTrue()
        ->and($result->path())->toBe($tmp);

    @unlink($tmp);
});

it('returns null for invalid class', function () {
    $item = mediaItemDefaults([
        'class' => stdClass::class,
        'base64' => base64_encode('data'),
        'mime' => 'image/png',
    ]);

    $result = invokeRestoreMediaItem($item);

    expect($result)->toBeNull();
});

it('returns null when no source matches', function () {
    $item = mediaItemDefaults([
        'class' => Image::class,
    ]);

    $result = invokeRestoreMediaItem($item);

    expect($result)->toBeNull();
});

// ─── restoreMedia ───────────────────────────────────────────────────────────

it('restoreMedia filters null items', function () {
    $items = [
        mediaItemDefaults([
            'class' => Image::class,
            'url' => 'https://example.com/valid.jpg',
        ]),
        mediaItemDefaults([
            'class' => stdClass::class,
            'url' => 'https://example.com/invalid.jpg',
        ]),
        mediaItemDefaults([
            'class' => Audio::class,
            'base64' => base64_encode('audio-data'),
            'mime' => 'audio/mpeg',
        ]),
    ];

    $result = invokeRestoreMedia($items);

    expect($result)
        ->toHaveCount(2)
        ->and($result[0])->toBeInstanceOf(Image::class)
        ->and($result[1])->toBeInstanceOf(Audio::class);
});

// ─── Terminal match ─────────────────────────────────────────────────────────

it('throws for unknown terminal method', function () {
    registerQueueTestAgent(QueueTestMinimalAgent::class);

    AgentRequest::executeFromPayload([
        'key' => 'queue-minimal',
        'message' => null,
        'message_media' => [],
        'instructions' => null,
        'variables' => [],
        'meta' => [],
        'provider' => null,
        'model' => null,
        'max_tokens' => null,
        'temperature' => null,
        'max_steps' => null,
        'concurrent' => null,
        'provider_options' => [],
        'middleware' => [],
        'owner_type' => null,
        'owner_id' => null,
        'message_owner_type' => null,
        'message_owner_id' => null,
        'conversation_id' => null,
        'message_limit' => null,
        'respond_mode' => false,
        'retry_mode' => false,
    ], 'asInvalid');
})->throws(InvalidArgumentException::class, 'Unknown terminal method: asInvalid');

it('serializes an explicit cache override into the queue payload', function () {
    registerQueueTestAgent(QueueTestMinimalAgent::class);

    $payload = Atlas::agent('queue-minimal')->cache(false)->message('hi')->toQueuePayload();

    expect($payload)->toHaveKey('cache')
        ->and($payload['cache'])->toBeFalse();
});

it('leaves cache null in the payload when no override is set', function () {
    registerQueueTestAgent(QueueTestMinimalAgent::class);

    $payload = Atlas::agent('queue-minimal')->message('hi')->toQueuePayload();

    expect($payload['cache'])->toBeNull();
});

it('serializes a reasoning override into the queue payload', function () {
    registerQueueTestAgent(QueueTestMinimalAgent::class);

    $payload = Atlas::agent('queue-minimal')
        ->reasoning(ReasoningEffort::High, budgetTokens: 9000, includeSummary: true)
        ->message('hi')
        ->toQueuePayload();

    expect($payload['reasoning'])->toBe([
        'effort' => 'high',
        'budget_tokens' => 9000,
        'include_summary' => true,
    ]);
});

it('leaves reasoning null in the payload when no override is set', function () {
    registerQueueTestAgent(QueueTestMinimalAgent::class);

    $payload = Atlas::agent('queue-minimal')->message('hi')->toQueuePayload();

    expect($payload['reasoning'])->toBeNull();
});

it('restores a reasoning override when executing from a queue payload', function () {
    registerQueueTestAgent(QueueTestMinimalAgent::class);

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(text: true));
    $captured = null;
    $driver->shouldReceive('text')->once()->andReturnUsing(function ($req) use (&$captured) {
        $captured = $req;

        return new TextResponse('ok', new Usage(1, 1), FinishReason::Stop);
    });
    app(ProviderRegistryContract::class)->register('openai', fn () => $driver);

    AgentRequest::executeFromPayload(queuePayload([
        'reasoning' => ['effort' => 'high', 'budget_tokens' => 9000, 'include_summary' => true],
    ]), 'asText');

    expect($captured->reasoning)->not->toBeNull()
        ->and($captured->reasoning->effort)->toBe(ReasoningEffort::High)
        ->and($captured->reasoning->budgetTokens)->toBe(9000)
        ->and($captured->reasoning->includeSummary)->toBeTrue();
});

it('restores an explicit cache override when executing from a queue payload', function () {
    registerQueueTestAgent(QueueTestMinimalAgent::class);

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(text: true));
    $captured = null;
    $driver->shouldReceive('text')->once()->andReturnUsing(function ($req) use (&$captured) {
        $captured = $req;

        return new TextResponse('ok', new Usage(1, 1), FinishReason::Stop);
    });
    app(ProviderRegistryContract::class)->register('openai', fn () => $driver);

    // Default is on; if the override were lost the worker would re-enable caching.
    config(['atlas.prompt_cache' => true]);

    AgentRequest::executeFromPayload([
        'key' => 'queue-minimal',
        'message' => 'hi',
        'message_media' => [],
        'instructions' => null,
        'variables' => [],
        'meta' => [],
        'provider' => 'openai',
        'model' => 'gpt-4o',
        'max_tokens' => null,
        'temperature' => null,
        'max_steps' => null,
        'concurrent' => null,
        'cache' => false,
        'provider_options' => [],
        'middleware' => [],
        'owner_type' => null,
        'owner_id' => null,
        'message_owner_type' => null,
        'message_owner_id' => null,
        'conversation_id' => null,
        'message_limit' => null,
        'respond_mode' => false,
        'retry_mode' => false,
    ], 'asText');

    expect($captured->cache)->toBeFalse();
});

it('restores a tool choice when executing from a queue payload', function () {
    registerQueueTestAgent(QueueTestMinimalAgent::class);

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(text: true));
    $captured = null;
    $driver->shouldReceive('text')->once()->andReturnUsing(function ($req) use (&$captured) {
        $captured = $req;

        return new TextResponse('ok', new Usage(1, 1), FinishReason::Stop);
    });
    app(ProviderRegistryContract::class)->register('openai', fn () => $driver);

    AgentRequest::executeFromPayload([
        'key' => 'queue-minimal',
        'message' => 'hi',
        'message_media' => [],
        'instructions' => null,
        'variables' => [],
        'meta' => [],
        'provider' => 'openai',
        'model' => 'gpt-4o',
        'max_tokens' => null,
        'temperature' => null,
        'max_steps' => null,
        'concurrent' => null,
        'cache' => null,
        'tool_choice' => ['mode' => 'required', 'tool' => 'log_mood'],
        'provider_options' => [],
        'middleware' => [],
        'owner_type' => null,
        'owner_id' => null,
        'message_owner_type' => null,
        'message_owner_id' => null,
        'conversation_id' => null,
        'message_limit' => null,
        'respond_mode' => false,
        'retry_mode' => false,
    ], 'asText');

    expect($captured->toolChoice)->toBeInstanceOf(ToolChoice::class)
        ->and($captured->toolChoice->mode)->toBe(ToolChoiceMode::Required)
        ->and($captured->toolChoice->tool)->toBe('log_mood');
});

// ─── restoreMediaItem: remaining source branches ─────────────────────────────

it('restores media from a file id', function () {
    $item = mediaItemDefaults([
        'class' => Image::class,
        'file_id' => 'file-abc123',
    ]);

    $result = invokeRestoreMediaItem($item);

    expect($result)->toBeInstanceOf(Image::class)
        ->and($result->isFileId())->toBeTrue()
        ->and($result->fileId())->toBe('file-abc123');
});

it('restores media from storage', function () {
    $item = mediaItemDefaults([
        'class' => Image::class,
        'storage_path' => 'uploads/cat.png',
        'storage_disk' => 'local',
    ]);

    $result = invokeRestoreMediaItem($item);

    expect($result)->toBeInstanceOf(Image::class)
        ->and($result->isStorage())->toBeTrue()
        ->and($result->storagePath())->toBe('uploads/cat.png')
        ->and($result->storageDisk())->toBe('local');
});

// ─── executeFromPayload: restore branches ────────────────────────────────────

it('merges the execution id into request meta', function () {
    registerQueueTestAgent(QueueTestMinimalAgent::class);
    $fake = new AtlasFake(app(ProviderRegistryContract::class), [TextResponseFake::make()->withText('ok')]);

    AgentRequest::executeFromPayload(queuePayload(['meta' => ['source' => 'queue']]), 'asText', executionId: 4242);

    $captured = $fake->recorded()[0]->request;
    expect($captured->meta)->toHaveKey('execution_id')
        ->and($captured->meta['execution_id'])->toBe(4242)
        ->and($captured->meta['source'])->toBe('queue');
});

it('restores middleware from the payload', function () {
    registerQueueTestAgent(QueueTestMinimalAgent::class);
    $fake = new AtlasFake(app(ProviderRegistryContract::class), [TextResponseFake::make()->withText('ok')]);

    AgentRequest::executeFromPayload(queuePayload(['middleware' => ['App\\Middleware\\Trace']]), 'asText');

    expect($fake->recorded()[0]->request->middleware)->toBe(['App\\Middleware\\Trace']);
});

it('restores the conversation id from the payload', function () {
    registerQueueTestAgent(QueueTestMinimalAgent::class);
    new AtlasFake(app(ProviderRegistryContract::class), [TextResponseFake::make()->withText('ok')]);

    $result = AgentRequest::executeFromPayload(queuePayload(['conversation_id' => 7]), 'asText');

    expect($result)->toBeInstanceOf(TextResponse::class);
});

it('restores respond and retry mode from the payload', function () {
    registerQueueTestAgent(QueueTestMinimalAgent::class);
    new AtlasFake(app(ProviderRegistryContract::class), [TextResponseFake::make()->withText('ok')]);

    $result = AgentRequest::executeFromPayload(
        queuePayload(['respond_mode' => true, 'retry_mode' => true]),
        'asText',
    );

    expect($result)->toBeInstanceOf(TextResponse::class);
});

it('applies a broadcast channel when one is provided', function () {
    registerQueueTestAgent(QueueTestMinimalAgent::class);
    new AtlasFake(app(ProviderRegistryContract::class), [StreamResponseFake::make()->withText('s')]);

    $result = AgentRequest::executeFromPayload(
        queuePayload(),
        'asStream',
        broadcastChannel: new PrivateChannel('atlas.test'),
    );

    expect($result)->toBeInstanceOf(StreamResponse::class);
});

// ─── executeFromPayload: terminal dispatch ───────────────────────────────────

it('dispatches the asStream terminal', function () {
    registerQueueTestAgent(QueueTestMinimalAgent::class);
    new AtlasFake(app(ProviderRegistryContract::class), [StreamResponseFake::make()->withText('streamed')]);

    $result = AgentRequest::executeFromPayload(queuePayload(), 'asStream');

    expect($result)->toBeInstanceOf(StreamResponse::class);
});

it('dispatches the asStructured terminal', function () {
    registerQueueTestAgent(QueueTestMinimalAgent::class);
    new AtlasFake(app(ProviderRegistryContract::class), [StructuredResponseFake::make()->withStructured(['ok' => true])]);

    $result = AgentRequest::executeFromPayload(queuePayload(), 'asStructured');

    expect($result)->toBeInstanceOf(StructuredResponse::class);
});

// ─── buildRequest: model guard ───────────────────────────────────────────────

it('throws when no model can be resolved for the agent', function () {
    registerQueueTestAgent(QueueTestNoModelAgent::class);
    config(['atlas.defaults.text' => ['provider' => 'openai', 'model' => null]]);
    AtlasConfig::refresh();
    new AtlasFake(app(ProviderRegistryContract::class), [TextResponseFake::make()->withText('x')]);

    Atlas::agent('queue-no-model')->message('hi')->asText();
})->throws(AtlasException::class, 'agent model');
