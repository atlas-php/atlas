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

it('defaults cache to false', function () {
    $request = new TextRequest(
        model: 'gpt-4o',
        instructions: null,
        message: 'Hi',
        messageMedia: [],
        messages: [],
        maxTokens: null,
        temperature: null,
        schema: null,
        tools: [],
        providerTools: [],
        providerOptions: [],
    );

    expect($request->cache)->toBeFalse();
});

it('preserves the cache flag across every with* transformation', function () {
    // respond() and the executor rebuild the request via these methods; the
    // cache flag must survive or caching silently turns off mid-turn.
    $request = new TextRequest(
        model: 'gpt-4o',
        instructions: 'Be helpful',
        message: 'Hi',
        messageMedia: [],
        messages: [new UserMessage('first')],
        maxTokens: null,
        temperature: null,
        schema: null,
        tools: [],
        providerTools: [],
        providerOptions: [],
        cache: true,
    );

    expect($request->withAppendedMessages([new UserMessage('x')])->cache)->toBeTrue()
        ->and($request->withReplacedTools([])->cache)->toBeTrue()
        ->and($request->withClearedMessage()->cache)->toBeTrue()
        ->and($request->withReplacedMessages([])->cache)->toBeTrue();
});
