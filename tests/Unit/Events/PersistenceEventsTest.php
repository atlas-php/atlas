<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\Role;
use Atlasphp\Atlas\Events\ConversationMessageStored;

// ─── ConversationMessageStored ─────────────────────────────────────────────

it('ConversationMessageStored stores conversationId, messageId, role, and agent', function () {
    $event = new ConversationMessageStored(
        conversationId: 1,
        messageId: 42,
        role: Role::User,
        agent: 'research-agent',
    );

    expect($event->conversationId)->toBe(1)
        ->and($event->messageId)->toBe(42)
        ->and($event->role)->toBe(Role::User)
        ->and($event->agent)->toBe('research-agent');
});

it('ConversationMessageStored stores assistant role', function () {
    $event = new ConversationMessageStored(
        conversationId: 5,
        messageId: 100,
        role: Role::Assistant,
        agent: 'writer-agent',
    );

    expect($event->conversationId)->toBe(5)
        ->and($event->messageId)->toBe(100)
        ->and($event->role)->toBe(Role::Assistant)
        ->and($event->agent)->toBe('writer-agent');
});

it('ConversationMessageStored stores null agent', function () {
    $event = new ConversationMessageStored(
        conversationId: 3,
        messageId: 77,
        role: Role::User,
        agent: null,
    );

    expect($event->conversationId)->toBe(3)
        ->and($event->messageId)->toBe(77)
        ->and($event->role)->toBe(Role::User)
        ->and($event->agent)->toBeNull();
});
