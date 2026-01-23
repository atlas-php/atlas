<?php

declare(strict_types=1);

use Atlasphp\Atlas\Streaming\Events\ArtifactEvent;
use Atlasphp\Atlas\Streaming\StreamEvent;

test('ArtifactEvent has correct type', function () {
    $event = new ArtifactEvent(
        id: 'evt_123',
        timestamp: 1234567890,
        artifact: ['type' => 'code', 'content' => '<?php echo "hello";'],
        toolCallId: 'call_abc',
        toolName: 'code_generator',
        messageId: 'msg_xyz',
    );

    expect($event)->toBeInstanceOf(StreamEvent::class);
    expect($event->type())->toBe('artifact');
});

test('ArtifactEvent stores artifact data', function () {
    $artifact = [
        'type' => 'image',
        'content' => 'base64...',
        'mime_type' => 'image/png',
    ];

    $event = new ArtifactEvent(
        id: 'evt_123',
        timestamp: 1234567890,
        artifact: $artifact,
        toolCallId: 'call_abc',
        toolName: 'image_gen',
        messageId: 'msg_xyz',
    );

    expect($event->artifact)->toBe($artifact);
    expect($event->toolCallId)->toBe('call_abc');
    expect($event->toolName)->toBe('image_gen');
    expect($event->messageId)->toBe('msg_xyz');
});

test('ArtifactEvent converts to array', function () {
    $event = new ArtifactEvent(
        id: 'evt_123',
        timestamp: 1234567890,
        artifact: ['type' => 'text', 'content' => 'Hello'],
        toolCallId: 'call_abc',
        toolName: 'writer',
        messageId: 'msg_xyz',
    );

    expect($event->toArray())->toBe([
        'id' => 'evt_123',
        'type' => 'artifact',
        'timestamp' => 1234567890,
        'artifact' => ['type' => 'text', 'content' => 'Hello'],
        'tool_call_id' => 'call_abc',
        'tool_name' => 'writer',
        'message_id' => 'msg_xyz',
    ]);
});
