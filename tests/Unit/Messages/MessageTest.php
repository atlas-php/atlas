<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\Role;
use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\SystemMessage;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Messages\ToolResultMessage;
use Atlasphp\Atlas\Messages\UserMessage;

it('creates a SystemMessage with correct role', function () {
    $message = new SystemMessage('You are helpful.');

    expect($message->content)->toBe('You are helpful.');
    expect($message->role())->toBe(Role::System);
});

it('creates a UserMessage with correct role and default media', function () {
    $message = new UserMessage('Hello');

    expect($message->content)->toBe('Hello');
    expect($message->media)->toBe([]);
    expect($message->role())->toBe(Role::User);
});

it('creates an AssistantMessage with correct role and defaults', function () {
    $message = new AssistantMessage('Hi there');

    expect($message->content)->toBe('Hi there');
    expect($message->toolCalls)->toBe([]);
    expect($message->reasoning)->toBeNull();
    expect($message->role())->toBe(Role::Assistant);
});

it('creates an AssistantMessage with tool calls', function () {
    $toolCall = new ToolCall('call_1', 'search', ['q' => 'test']);
    $message = new AssistantMessage(toolCalls: [$toolCall], reasoning: 'thinking...');

    expect($message->content)->toBeNull();
    expect($message->toolCalls)->toHaveCount(1);
    expect($message->reasoning)->toBe('thinking...');
});

it('creates a ToolResultMessage with correct role', function () {
    $message = new ToolResultMessage('call_1', 'result data', 'search');

    expect($message->toolCallId)->toBe('call_1');
    expect($message->content)->toBe('result data');
    expect($message->toolName)->toBe('search');
    expect($message->role())->toBe(Role::Tool);
});
