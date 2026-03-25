<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\Role;
use Atlasphp\Atlas\Messages\ToolResultMessage;

it('stores toolCallId, content, and toolName', function () {
    $message = new ToolResultMessage('tc-1', 'result content', 'search');

    expect($message->toolCallId)->toBe('tc-1');
    expect($message->content)->toBe('result content');
    expect($message->toolName)->toBe('search');
    expect($message->role())->toBe(Role::Tool);
});

it('defaults isError to false', function () {
    $message = new ToolResultMessage('tc-1', 'ok');

    expect($message->isError)->toBeFalse();
});

it('stores isError flag when true', function () {
    $message = new ToolResultMessage('tc-1', 'error occurred', 'search', isError: true);

    expect($message->isError)->toBeTrue();
});
