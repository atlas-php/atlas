<?php

declare(strict_types=1);

use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Requests\TextRequest;

it('constructs with all parameters', function () {
    $request = new TextRequest(
        model: 'gpt-4o',
        instructions: 'Be helpful',
        message: 'Hello',
        messageMedia: [],
        messages: [],
        maxTokens: 1000,
        temperature: 0.7,
        schema: null,
        tools: [],
        providerTools: [],
        providerOptions: [],
    );

    expect($request->model)->toBe('gpt-4o');
    expect($request->instructions)->toBe('Be helpful');
    expect($request->maxTokens)->toBe(1000);
});

it('returns a new instance from withAppendedMessages', function () {
    $original = new TextRequest(
        model: 'gpt-4o',
        instructions: null,
        message: null,
        messageMedia: [],
        messages: [new UserMessage('first')],
        maxTokens: null,
        temperature: null,
        schema: null,
        tools: [],
        providerTools: [],
        providerOptions: [],
    );

    $appended = $original->withAppendedMessages([new UserMessage('second')]);

    expect($appended)->not->toBe($original);
    expect($original->messages)->toHaveCount(1);
    expect($appended->messages)->toHaveCount(2);
});
