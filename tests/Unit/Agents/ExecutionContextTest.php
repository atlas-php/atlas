<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Support\ExecutionContext;

test('it creates with default empty values', function () {
    $context = new ExecutionContext;

    expect($context->messages)->toBe([]);
    expect($context->variables)->toBe([]);
    expect($context->metadata)->toBe([]);
});

test('it creates with provided values', function () {
    $messages = [['role' => 'user', 'content' => 'Hello']];
    $variables = ['user_name' => 'John'];
    $metadata = ['session_id' => '123'];

    $context = new ExecutionContext($messages, $variables, $metadata);

    expect($context->messages)->toBe($messages);
    expect($context->variables)->toBe($variables);
    expect($context->metadata)->toBe($metadata);
});

test('it creates new instance with messages', function () {
    $context = new ExecutionContext;
    $messages = [['role' => 'user', 'content' => 'Hello']];

    $newContext = $context->withMessages($messages);

    expect($newContext)->not->toBe($context);
    expect($newContext->messages)->toBe($messages);
    expect($context->messages)->toBe([]);
});

test('it creates new instance with variables', function () {
    $context = new ExecutionContext;
    $variables = ['user_name' => 'John'];

    $newContext = $context->withVariables($variables);

    expect($newContext)->not->toBe($context);
    expect($newContext->variables)->toBe($variables);
});

test('it creates new instance with metadata', function () {
    $context = new ExecutionContext;
    $metadata = ['key' => 'value'];

    $newContext = $context->withMetadata($metadata);

    expect($newContext)->not->toBe($context);
    expect($newContext->metadata)->toBe($metadata);
});

test('it merges variables preserving existing', function () {
    $context = new ExecutionContext([], ['a' => 1]);

    $newContext = $context->mergeVariables(['b' => 2]);

    expect($newContext->variables)->toBe(['a' => 1, 'b' => 2]);
});

test('it merges metadata preserving existing', function () {
    $context = new ExecutionContext([], [], ['a' => 1]);

    $newContext = $context->mergeMetadata(['b' => 2]);

    expect($newContext->metadata)->toBe(['a' => 1, 'b' => 2]);
});

test('it gets variable with default', function () {
    $context = new ExecutionContext([], ['key' => 'value']);

    expect($context->getVariable('key'))->toBe('value');
    expect($context->getVariable('missing', 'default'))->toBe('default');
});

test('it gets meta with default', function () {
    $context = new ExecutionContext([], [], ['key' => 'value']);

    expect($context->getMeta('key'))->toBe('value');
    expect($context->getMeta('missing', 'default'))->toBe('default');
});

test('it reports hasMessages correctly', function () {
    $empty = new ExecutionContext;
    $withMessages = new ExecutionContext([['role' => 'user', 'content' => 'Hi']]);

    expect($empty->hasMessages())->toBeFalse();
    expect($withMessages->hasMessages())->toBeTrue();
});

test('it reports hasVariable correctly', function () {
    $context = new ExecutionContext([], ['key' => 'value']);

    expect($context->hasVariable('key'))->toBeTrue();
    expect($context->hasVariable('missing'))->toBeFalse();
});

test('it reports hasMeta correctly', function () {
    $context = new ExecutionContext([], [], ['key' => 'value']);

    expect($context->hasMeta('key'))->toBeTrue();
    expect($context->hasMeta('missing'))->toBeFalse();
});
