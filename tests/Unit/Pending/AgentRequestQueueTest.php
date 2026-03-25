<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Agents\AgentRegistry;
use Atlasphp\Atlas\Input\Audio;
use Atlasphp\Atlas\Input\Image;
use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Pending\AgentRequest;

// ─── Test agent ─────────────────────────────────────────────────────────────

class QueueTestMinimalAgent extends Agent
{
    public function key(): string
    {
        return 'queue-minimal';
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
