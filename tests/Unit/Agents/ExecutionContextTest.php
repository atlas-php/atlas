<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Support\ExecutionContext;

test('it creates with default empty values', function () {
    $context = new ExecutionContext;

    expect($context->messages)->toBe([]);
    expect($context->variables)->toBe([]);
    expect($context->metadata)->toBe([]);
    expect($context->providerOverride)->toBeNull();
    expect($context->modelOverride)->toBeNull();
    expect($context->prismCalls)->toBe([]);
    expect($context->prismMedia)->toBe([]);
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

test('it creates with all constructor parameters', function () {
    $messages = [['role' => 'user', 'content' => 'Hello']];
    $variables = ['user_name' => 'John'];
    $metadata = ['session_id' => '123'];
    $prismCalls = [['method' => 'withMaxSteps', 'args' => [10]]];

    $context = new ExecutionContext(
        messages: $messages,
        variables: $variables,
        metadata: $metadata,
        providerOverride: 'anthropic',
        modelOverride: 'claude-3-opus',
        prismCalls: $prismCalls,
    );

    expect($context->messages)->toBe($messages);
    expect($context->variables)->toBe($variables);
    expect($context->metadata)->toBe($metadata);
    expect($context->providerOverride)->toBe('anthropic');
    expect($context->modelOverride)->toBe('claude-3-opus');
    expect($context->prismCalls)->toBe($prismCalls);
});

test('it gets variable with default', function () {
    $context = new ExecutionContext(variables: ['key' => 'value']);

    expect($context->getVariable('key'))->toBe('value');
    expect($context->getVariable('missing', 'default'))->toBe('default');
});

test('it gets meta with default', function () {
    $context = new ExecutionContext(metadata: ['key' => 'value']);

    expect($context->getMeta('key'))->toBe('value');
    expect($context->getMeta('missing', 'default'))->toBe('default');
});

test('it reports hasMessages correctly', function () {
    $empty = new ExecutionContext;
    $withMessages = new ExecutionContext(messages: [['role' => 'user', 'content' => 'Hi']]);

    expect($empty->hasMessages())->toBeFalse();
    expect($withMessages->hasMessages())->toBeTrue();
});

test('it reports hasVariable correctly', function () {
    $context = new ExecutionContext(variables: ['key' => 'value']);

    expect($context->hasVariable('key'))->toBeTrue();
    expect($context->hasVariable('missing'))->toBeFalse();
});

test('it reports hasMeta correctly', function () {
    $context = new ExecutionContext(metadata: ['key' => 'value']);

    expect($context->hasMeta('key'))->toBeTrue();
    expect($context->hasMeta('missing'))->toBeFalse();
});

test('it creates with provider and model overrides', function () {
    $context = new ExecutionContext(providerOverride: 'anthropic', modelOverride: 'claude-3-opus');

    expect($context->providerOverride)->toBe('anthropic');
    expect($context->modelOverride)->toBe('claude-3-opus');
});

test('it reports hasProviderOverride correctly', function () {
    $withoutOverride = new ExecutionContext;
    $withOverride = new ExecutionContext(providerOverride: 'anthropic');

    expect($withoutOverride->hasProviderOverride())->toBeFalse();
    expect($withOverride->hasProviderOverride())->toBeTrue();
});

test('it reports hasModelOverride correctly', function () {
    $withoutOverride = new ExecutionContext;
    $withOverride = new ExecutionContext(modelOverride: 'gpt-4-turbo');

    expect($withoutOverride->hasModelOverride())->toBeFalse();
    expect($withOverride->hasModelOverride())->toBeTrue();
});

test('it creates with default empty prismMedia', function () {
    $context = new ExecutionContext;

    expect($context->prismMedia)->toBe([]);
});

test('it creates with provided prismMedia', function () {
    $mockImage = Mockery::mock(\Prism\Prism\ValueObjects\Media\Image::class);

    $context = new ExecutionContext(prismMedia: [$mockImage]);

    expect($context->prismMedia)->toBe([$mockImage]);
});

test('it reports hasAttachments correctly', function () {
    $mockImage = Mockery::mock(\Prism\Prism\ValueObjects\Media\Image::class);

    $empty = new ExecutionContext;
    $withMedia = new ExecutionContext(prismMedia: [$mockImage]);

    expect($empty->hasAttachments())->toBeFalse();
    expect($withMedia->hasAttachments())->toBeTrue();
});

test('it creates with prismCalls', function () {
    $prismCalls = [
        ['method' => 'withMaxSteps', 'args' => [10]],
        ['method' => 'withTemperature', 'args' => [0.7]],
    ];

    $context = new ExecutionContext(prismCalls: $prismCalls);

    expect($context->prismCalls)->toBe($prismCalls);
});

test('it creates with prismMessages', function () {
    $mockUserMessage = Mockery::mock(\Prism\Prism\ValueObjects\Messages\UserMessage::class);
    $mockAssistantMessage = Mockery::mock(\Prism\Prism\ValueObjects\Messages\AssistantMessage::class);

    $context = new ExecutionContext(prismMessages: [$mockUserMessage, $mockAssistantMessage]);

    expect($context->prismMessages)->toBe([$mockUserMessage, $mockAssistantMessage]);
});

test('it reports hasPrismMessages correctly', function () {
    $mockUserMessage = Mockery::mock(\Prism\Prism\ValueObjects\Messages\UserMessage::class);

    $empty = new ExecutionContext;
    $withPrismMessages = new ExecutionContext(prismMessages: [$mockUserMessage]);

    expect($empty->hasPrismMessages())->toBeFalse();
    expect($withPrismMessages->hasPrismMessages())->toBeTrue();
});

test('hasMessages returns true for either array or prism messages', function () {
    $mockUserMessage = Mockery::mock(\Prism\Prism\ValueObjects\Messages\UserMessage::class);

    $empty = new ExecutionContext;
    $withArrayMessages = new ExecutionContext(messages: [['role' => 'user', 'content' => 'Hi']]);
    $withPrismMessages = new ExecutionContext(prismMessages: [$mockUserMessage]);

    expect($empty->hasMessages())->toBeFalse();
    expect($withArrayMessages->hasMessages())->toBeTrue();
    expect($withPrismMessages->hasMessages())->toBeTrue();
});

test('it reports hasPrismCalls correctly', function () {
    $empty = new ExecutionContext;
    $withCalls = new ExecutionContext(prismCalls: [
        ['method' => 'withMaxSteps', 'args' => [10]],
    ]);

    expect($empty->hasPrismCalls())->toBeFalse();
    expect($withCalls->hasPrismCalls())->toBeTrue();
});

test('it reports hasSchemaCall correctly', function () {
    $empty = new ExecutionContext;
    $withSchema = new ExecutionContext(prismCalls: [
        ['method' => 'withSchema', 'args' => ['mock-schema']],
    ]);
    $withOtherCalls = new ExecutionContext(prismCalls: [
        ['method' => 'withMaxSteps', 'args' => [10]],
    ]);

    expect($empty->hasSchemaCall())->toBeFalse();
    expect($withSchema->hasSchemaCall())->toBeTrue();
    expect($withOtherCalls->hasSchemaCall())->toBeFalse();
});

test('it gets schema from prism calls', function () {
    $mockSchema = new \stdClass;
    $mockSchema->name = 'test-schema';

    $contextWithSchema = new ExecutionContext(prismCalls: [
        ['method' => 'withMaxSteps', 'args' => [10]],
        ['method' => 'withSchema', 'args' => [$mockSchema]],
    ]);

    $contextWithoutSchema = new ExecutionContext(prismCalls: [
        ['method' => 'withMaxSteps', 'args' => [10]],
    ]);

    expect($contextWithSchema->getSchemaFromCalls())->toBe($mockSchema);
    expect($contextWithoutSchema->getSchemaFromCalls())->toBeNull();
});

test('it gets prism calls without schema', function () {
    $mockSchema = new \stdClass;

    $context = new ExecutionContext(prismCalls: [
        ['method' => 'withMaxSteps', 'args' => [10]],
        ['method' => 'withSchema', 'args' => [$mockSchema]],
        ['method' => 'usingTemperature', 'args' => [0.7]],
    ]);

    $callsWithoutSchema = $context->getPrismCallsWithoutSchema();

    expect($callsWithoutSchema)->toHaveCount(2);
    expect($callsWithoutSchema[0]['method'])->toBe('withMaxSteps');
    expect($callsWithoutSchema[1]['method'])->toBe('usingTemperature');
});

test('getPrismCallsWithoutSchema returns empty array when no calls', function () {
    $context = new ExecutionContext;

    expect($context->getPrismCallsWithoutSchema())->toBe([]);
});

test('getPrismCallsWithoutSchema returns all calls when no schema', function () {
    $context = new ExecutionContext(prismCalls: [
        ['method' => 'withMaxSteps', 'args' => [10]],
        ['method' => 'usingTemperature', 'args' => [0.7]],
    ]);

    $calls = $context->getPrismCallsWithoutSchema();

    expect($calls)->toHaveCount(2);
    expect($calls[0]['method'])->toBe('withMaxSteps');
    expect($calls[1]['method'])->toBe('usingTemperature');
});
