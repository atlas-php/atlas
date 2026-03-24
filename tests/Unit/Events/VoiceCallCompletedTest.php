<?php

declare(strict_types=1);

use Atlasphp\Atlas\Events\VoiceCallCompleted;

it('constructs with all properties', function () {
    $transcript = [
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi there!'],
    ];

    $event = new VoiceCallCompleted(
        voiceCallId: 42,
        conversationId: 7,
        sessionId: 'rt_abc123',
        transcript: $transcript,
        durationMs: 15000,
    );

    expect($event->voiceCallId)->toBe(42)
        ->and($event->conversationId)->toBe(7)
        ->and($event->sessionId)->toBe('rt_abc123')
        ->and($event->transcript)->toBe($transcript)
        ->and($event->transcript)->toHaveCount(2)
        ->and($event->durationMs)->toBe(15000);
});

it('accepts null conversationId', function () {
    $event = new VoiceCallCompleted(
        voiceCallId: 1,
        conversationId: null,
        sessionId: 'rt_456',
        transcript: [],
        durationMs: 5000,
    );

    expect($event->conversationId)->toBeNull();
});

it('accepts null durationMs', function () {
    $event = new VoiceCallCompleted(
        voiceCallId: 10,
        conversationId: 3,
        sessionId: 'rt_789',
        transcript: [['role' => 'user', 'content' => 'Test']],
        durationMs: null,
    );

    expect($event->durationMs)->toBeNull();
});

it('accepts empty transcript array', function () {
    $event = new VoiceCallCompleted(
        voiceCallId: 5,
        conversationId: null,
        sessionId: 'rt_def',
        transcript: [],
        durationMs: null,
    );

    expect($event->voiceCallId)->toBe(5)
        ->and($event->conversationId)->toBeNull()
        ->and($event->transcript)->toBe([])
        ->and($event->durationMs)->toBeNull();
});
