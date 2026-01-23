<?php

declare(strict_types=1);

use Atlasphp\Atlas\Streaming\Events\CitationEvent;
use Atlasphp\Atlas\Streaming\StreamEvent;

test('CitationEvent has correct type', function () {
    $event = new CitationEvent(
        id: 'evt_123',
        timestamp: 1234567890,
        citation: ['source_type' => 'document', 'source' => 'doc.pdf'],
        messageId: 'msg_abc',
    );

    expect($event)->toBeInstanceOf(StreamEvent::class);
    expect($event->type())->toBe('citation');
});

test('CitationEvent stores citation data', function () {
    $citation = [
        'source_type' => 'web',
        'source' => 'https://example.com',
        'source_text' => 'Some quoted text',
    ];

    $event = new CitationEvent(
        id: 'evt_123',
        timestamp: 1234567890,
        citation: $citation,
        messageId: 'msg_abc',
    );

    expect($event->citation)->toBe($citation);
    expect($event->messageId)->toBe('msg_abc');
});

test('CitationEvent includes optional fields', function () {
    $metadata = ['confidence' => 0.95];

    $event = new CitationEvent(
        id: 'evt_123',
        timestamp: 1234567890,
        citation: ['source' => 'test'],
        messageId: 'msg_abc',
        blockIndex: 2,
        metadata: $metadata,
    );

    expect($event->blockIndex)->toBe(2);
    expect($event->metadata)->toBe($metadata);
});

test('CitationEvent converts to array', function () {
    $event = new CitationEvent(
        id: 'evt_123',
        timestamp: 1234567890,
        citation: ['source' => 'test'],
        messageId: 'msg_abc',
        blockIndex: 1,
        metadata: null,
    );

    expect($event->toArray())->toBe([
        'id' => 'evt_123',
        'type' => 'citation',
        'timestamp' => 1234567890,
        'citation' => ['source' => 'test'],
        'message_id' => 'msg_abc',
        'block_index' => 1,
        'metadata' => null,
    ]);
});
