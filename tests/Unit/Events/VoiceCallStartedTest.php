<?php

declare(strict_types=1);

use Atlasphp\Atlas\Events\VoiceCallStarted;

it('constructs with all properties', function () {
    $event = new VoiceCallStarted(
        voiceCallId: 42,
        conversationId: 7,
        sessionId: 'rt_abc123',
        provider: 'openai',
        agent: 'support-agent',
    );

    expect($event->voiceCallId)->toBe(42)
        ->and($event->conversationId)->toBe(7)
        ->and($event->sessionId)->toBe('rt_abc123')
        ->and($event->provider)->toBe('openai')
        ->and($event->agent)->toBe('support-agent');
});

it('accepts null conversationId', function () {
    $event = new VoiceCallStarted(
        voiceCallId: 1,
        conversationId: null,
        sessionId: 'rt_456',
        provider: 'openai',
        agent: 'my-agent',
    );

    expect($event->conversationId)->toBeNull();
});

it('accepts null agent', function () {
    $event = new VoiceCallStarted(
        voiceCallId: 10,
        conversationId: 3,
        sessionId: 'rt_789',
        provider: 'openai',
        agent: null,
    );

    expect($event->agent)->toBeNull();
});

it('accepts null conversationId and agent simultaneously', function () {
    $event = new VoiceCallStarted(
        voiceCallId: 5,
        conversationId: null,
        sessionId: 'rt_def',
        provider: 'anthropic',
        agent: null,
    );

    expect($event->voiceCallId)->toBe(5)
        ->and($event->conversationId)->toBeNull()
        ->and($event->sessionId)->toBe('rt_def')
        ->and($event->provider)->toBe('anthropic')
        ->and($event->agent)->toBeNull();
});
