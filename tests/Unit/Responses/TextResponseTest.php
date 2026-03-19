<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;

it('returns false for hasToolCalls when empty', function () {
    $response = new TextResponse(
        text: 'Hello',
        usage: new Usage(10, 20),
        finishReason: FinishReason::Stop,
    );

    expect($response->hasToolCalls())->toBeFalse();
});

it('returns true for hasToolCalls when populated', function () {
    $response = new TextResponse(
        text: '',
        usage: new Usage(10, 20),
        finishReason: FinishReason::ToolCalls,
        toolCalls: [new ToolCall('call_1', 'search', ['q' => 'test'])],
    );

    expect($response->hasToolCalls())->toBeTrue();
});

it('converts to an AssistantMessage', function () {
    $response = new TextResponse(
        text: 'The answer is 42.',
        usage: new Usage(10, 20),
        finishReason: FinishReason::Stop,
        reasoning: 'I calculated it.',
    );

    $message = $response->toMessage();

    expect($message)->toBeInstanceOf(AssistantMessage::class);
    expect($message->content)->toBe('The answer is 42.');
    expect($message->reasoning)->toBe('I calculated it.');
});
