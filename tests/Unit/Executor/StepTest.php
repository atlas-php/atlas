<?php

declare(strict_types=1);

use Atlasphp\Atlas\Executor\Step;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Responses\Usage;

it('stores all properties', function () {
    $toolCall = new ToolCall('tc-1', 'search', ['query' => 'test']);
    $usage = new Usage(100, 50);

    $step = new Step(
        text: 'Hello',
        toolCalls: [$toolCall],
        toolResults: [],
        usage: $usage,
    );

    expect($step->text)->toBe('Hello');
    expect($step->toolCalls)->toBe([$toolCall]);
    expect($step->toolResults)->toBe([]);
    expect($step->usage)->toBe($usage);
});

it('returns true for hasToolCalls when tool calls exist', function () {
    $step = new Step(
        text: null,
        toolCalls: [new ToolCall('tc-1', 'search', [])],
        toolResults: [],
        usage: new Usage(10, 20),
    );

    expect($step->hasToolCalls())->toBeTrue();
});

it('returns false for hasToolCalls when no tool calls', function () {
    $step = new Step(
        text: 'Final answer',
        toolCalls: [],
        toolResults: [],
        usage: new Usage(10, 20),
    );

    expect($step->hasToolCalls())->toBeFalse();
});
