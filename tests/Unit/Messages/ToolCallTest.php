<?php

declare(strict_types=1);

use Atlasphp\Atlas\Messages\ToolCall;

it('constructs with id, name, and arguments', function () {
    $toolCall = new ToolCall('call_123', 'search', ['query' => 'test']);

    expect($toolCall->id)->toBe('call_123');
    expect($toolCall->name)->toBe('search');
    expect($toolCall->arguments)->toBe(['query' => 'test']);
});
