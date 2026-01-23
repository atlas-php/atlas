<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Atlasphp\Atlas\Tools\Enums\ToolChoice;

test('it stores tool choice enum', function () {
    $context = new ExecutionContext(toolChoice: ToolChoice::Any);

    expect($context->toolChoice)->toBe(ToolChoice::Any);
    expect($context->hasToolChoice())->toBeTrue();
});

test('it stores tool choice string', function () {
    $context = new ExecutionContext(toolChoice: 'calculator');

    expect($context->toolChoice)->toBe('calculator');
    expect($context->hasToolChoice())->toBeTrue();
});

test('it reports no tool choice when null', function () {
    $context = new ExecutionContext;

    expect($context->toolChoice)->toBeNull();
    expect($context->hasToolChoice())->toBeFalse();
});

test('withToolChoice creates new context with enum', function () {
    $original = new ExecutionContext;
    $modified = $original->withToolChoice(ToolChoice::None);

    expect($original->toolChoice)->toBeNull();
    expect($modified->toolChoice)->toBe(ToolChoice::None);
});

test('withToolChoice creates new context with string', function () {
    $original = new ExecutionContext;
    $modified = $original->withToolChoice('search');

    expect($modified->toolChoice)->toBe('search');
});

test('withToolChoice preserves other properties', function () {
    $original = new ExecutionContext(
        messages: [['role' => 'user', 'content' => 'Hello']],
        variables: ['name' => 'Test'],
        metadata: ['key' => 'value'],
        providerOverride: 'openai',
        modelOverride: 'gpt-4',
        currentAttachments: [['type' => 'image', 'source' => 'url', 'data' => 'test']],
    );

    $modified = $original->withToolChoice(ToolChoice::Any);

    expect($modified->messages)->toBe($original->messages);
    expect($modified->variables)->toBe($original->variables);
    expect($modified->metadata)->toBe($original->metadata);
    expect($modified->providerOverride)->toBe($original->providerOverride);
    expect($modified->modelOverride)->toBe($original->modelOverride);
    expect($modified->currentAttachments)->toBe($original->currentAttachments);
});

test('other with methods preserve tool choice', function () {
    $original = new ExecutionContext(toolChoice: ToolChoice::Any);

    $withMessages = $original->withMessages([['role' => 'user', 'content' => 'Hi']]);
    $withVariables = $original->withVariables(['x' => 1]);
    $withMetadata = $original->withMetadata(['y' => 2]);
    $withProvider = $original->withProviderOverride('anthropic');
    $withModel = $original->withModelOverride('claude-3');

    expect($withMessages->toolChoice)->toBe(ToolChoice::Any);
    expect($withVariables->toolChoice)->toBe(ToolChoice::Any);
    expect($withMetadata->toolChoice)->toBe(ToolChoice::Any);
    expect($withProvider->toolChoice)->toBe(ToolChoice::Any);
    expect($withModel->toolChoice)->toBe(ToolChoice::Any);
});

test('merge methods preserve tool choice', function () {
    $original = new ExecutionContext(
        variables: ['a' => 1],
        metadata: ['b' => 2],
        toolChoice: ToolChoice::None,
    );

    $mergedVars = $original->mergeVariables(['c' => 3]);
    $mergedMeta = $original->mergeMetadata(['d' => 4]);

    expect($mergedVars->toolChoice)->toBe(ToolChoice::None);
    expect($mergedMeta->toolChoice)->toBe(ToolChoice::None);
});
