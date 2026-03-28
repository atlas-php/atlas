<?php

declare(strict_types=1);

use Atlasphp\Atlas\Middleware\Contracts\AgentMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\AudioMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\EmbedMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\ImageMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\ProviderMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\StepMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\TextMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\ToolMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\VideoMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\VoiceHttpMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\VoiceMiddleware;
use Atlasphp\Atlas\Persistence\Middleware\PersistConversation;
use Atlasphp\Atlas\Persistence\Middleware\TrackExecution;
use Atlasphp\Atlas\Persistence\Middleware\TrackProviderCall;
use Atlasphp\Atlas\Persistence\Middleware\TrackStep;
use Atlasphp\Atlas\Persistence\Middleware\TrackToolCall;

// Persistence middleware interface verification

it('PersistConversation implements AgentMiddleware', function () {
    expect(is_subclass_of(PersistConversation::class, AgentMiddleware::class))->toBeTrue();
});

it('TrackExecution implements AgentMiddleware', function () {
    expect(is_subclass_of(TrackExecution::class, AgentMiddleware::class))->toBeTrue();
});

it('TrackStep implements StepMiddleware', function () {
    expect(is_subclass_of(TrackStep::class, StepMiddleware::class))->toBeTrue();
});

it('TrackToolCall implements ToolMiddleware', function () {
    expect(is_subclass_of(TrackToolCall::class, ToolMiddleware::class))->toBeTrue();
});

it('TrackProviderCall implements ProviderMiddleware', function () {
    expect(is_subclass_of(TrackProviderCall::class, ProviderMiddleware::class))->toBeTrue();
});

// Modality sub-interface hierarchy verification

it('TextMiddleware extends ProviderMiddleware', function () {
    expect(is_subclass_of(TextMiddleware::class, ProviderMiddleware::class))->toBeTrue();
});

it('ImageMiddleware extends ProviderMiddleware', function () {
    expect(is_subclass_of(ImageMiddleware::class, ProviderMiddleware::class))->toBeTrue();
});

it('AudioMiddleware extends ProviderMiddleware', function () {
    expect(is_subclass_of(AudioMiddleware::class, ProviderMiddleware::class))->toBeTrue();
});

it('VideoMiddleware extends ProviderMiddleware', function () {
    expect(is_subclass_of(VideoMiddleware::class, ProviderMiddleware::class))->toBeTrue();
});

it('VoiceMiddleware extends ProviderMiddleware', function () {
    expect(is_subclass_of(VoiceMiddleware::class, ProviderMiddleware::class))->toBeTrue();
});

it('EmbedMiddleware extends ProviderMiddleware', function () {
    expect(is_subclass_of(EmbedMiddleware::class, ProviderMiddleware::class))->toBeTrue();
});

// VoiceHttpMiddleware is a separate system

it('VoiceHttpMiddleware does not extend ProviderMiddleware', function () {
    expect(is_subclass_of(VoiceHttpMiddleware::class, ProviderMiddleware::class))->toBeFalse();
});
