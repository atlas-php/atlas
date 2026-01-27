<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Support\AgentContext;

test('it creates with default empty values', function () {
    $context = new AgentContext;

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

    $context = new AgentContext($messages, $variables, $metadata);

    expect($context->messages)->toBe($messages);
    expect($context->variables)->toBe($variables);
    expect($context->metadata)->toBe($metadata);
});

test('it creates with all constructor parameters', function () {
    $messages = [['role' => 'user', 'content' => 'Hello']];
    $variables = ['user_name' => 'John'];
    $metadata = ['session_id' => '123'];
    $prismCalls = [['method' => 'withMaxSteps', 'args' => [10]]];

    $context = new AgentContext(
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
    $context = new AgentContext(variables: ['key' => 'value']);

    expect($context->getVariable('key'))->toBe('value');
    expect($context->getVariable('missing', 'default'))->toBe('default');
});

test('it gets meta with default', function () {
    $context = new AgentContext(metadata: ['key' => 'value']);

    expect($context->getMeta('key'))->toBe('value');
    expect($context->getMeta('missing', 'default'))->toBe('default');
});

test('it reports hasMessages correctly', function () {
    $empty = new AgentContext;
    $withMessages = new AgentContext(messages: [['role' => 'user', 'content' => 'Hi']]);

    expect($empty->hasMessages())->toBeFalse();
    expect($withMessages->hasMessages())->toBeTrue();
});

test('it reports hasVariable correctly', function () {
    $context = new AgentContext(variables: ['key' => 'value']);

    expect($context->hasVariable('key'))->toBeTrue();
    expect($context->hasVariable('missing'))->toBeFalse();
});

test('it reports hasMeta correctly', function () {
    $context = new AgentContext(metadata: ['key' => 'value']);

    expect($context->hasMeta('key'))->toBeTrue();
    expect($context->hasMeta('missing'))->toBeFalse();
});

test('it creates with provider and model overrides', function () {
    $context = new AgentContext(providerOverride: 'anthropic', modelOverride: 'claude-3-opus');

    expect($context->providerOverride)->toBe('anthropic');
    expect($context->modelOverride)->toBe('claude-3-opus');
});

test('it reports hasProviderOverride correctly', function () {
    $withoutOverride = new AgentContext;
    $withOverride = new AgentContext(providerOverride: 'anthropic');

    expect($withoutOverride->hasProviderOverride())->toBeFalse();
    expect($withOverride->hasProviderOverride())->toBeTrue();
});

test('it reports hasModelOverride correctly', function () {
    $withoutOverride = new AgentContext;
    $withOverride = new AgentContext(modelOverride: 'gpt-4-turbo');

    expect($withoutOverride->hasModelOverride())->toBeFalse();
    expect($withOverride->hasModelOverride())->toBeTrue();
});

test('it creates with default empty prismMedia', function () {
    $context = new AgentContext;

    expect($context->prismMedia)->toBe([]);
});

test('it creates with provided prismMedia', function () {
    $mockImage = Mockery::mock(\Prism\Prism\ValueObjects\Media\Image::class);

    $context = new AgentContext(prismMedia: [$mockImage]);

    expect($context->prismMedia)->toBe([$mockImage]);
});

test('it reports hasAttachments correctly', function () {
    $mockImage = Mockery::mock(\Prism\Prism\ValueObjects\Media\Image::class);

    $empty = new AgentContext;
    $withMedia = new AgentContext(prismMedia: [$mockImage]);

    expect($empty->hasAttachments())->toBeFalse();
    expect($withMedia->hasAttachments())->toBeTrue();
});

test('it creates with prismCalls', function () {
    $prismCalls = [
        ['method' => 'withMaxSteps', 'args' => [10]],
        ['method' => 'withTemperature', 'args' => [0.7]],
    ];

    $context = new AgentContext(prismCalls: $prismCalls);

    expect($context->prismCalls)->toBe($prismCalls);
});

test('it creates with prismMessages', function () {
    $mockUserMessage = Mockery::mock(\Prism\Prism\ValueObjects\Messages\UserMessage::class);
    $mockAssistantMessage = Mockery::mock(\Prism\Prism\ValueObjects\Messages\AssistantMessage::class);

    $context = new AgentContext(prismMessages: [$mockUserMessage, $mockAssistantMessage]);

    expect($context->prismMessages)->toBe([$mockUserMessage, $mockAssistantMessage]);
});

test('it reports hasPrismMessages correctly', function () {
    $mockUserMessage = Mockery::mock(\Prism\Prism\ValueObjects\Messages\UserMessage::class);

    $empty = new AgentContext;
    $withPrismMessages = new AgentContext(prismMessages: [$mockUserMessage]);

    expect($empty->hasPrismMessages())->toBeFalse();
    expect($withPrismMessages->hasPrismMessages())->toBeTrue();
});

test('hasMessages returns true for either array or prism messages', function () {
    $mockUserMessage = Mockery::mock(\Prism\Prism\ValueObjects\Messages\UserMessage::class);

    $empty = new AgentContext;
    $withArrayMessages = new AgentContext(messages: [['role' => 'user', 'content' => 'Hi']]);
    $withPrismMessages = new AgentContext(prismMessages: [$mockUserMessage]);

    expect($empty->hasMessages())->toBeFalse();
    expect($withArrayMessages->hasMessages())->toBeTrue();
    expect($withPrismMessages->hasMessages())->toBeTrue();
});

test('it reports hasPrismCalls correctly', function () {
    $empty = new AgentContext;
    $withCalls = new AgentContext(prismCalls: [
        ['method' => 'withMaxSteps', 'args' => [10]],
    ]);

    expect($empty->hasPrismCalls())->toBeFalse();
    expect($withCalls->hasPrismCalls())->toBeTrue();
});

test('it reports hasSchemaCall correctly', function () {
    $empty = new AgentContext;
    $withSchema = new AgentContext(prismCalls: [
        ['method' => 'withSchema', 'args' => ['mock-schema']],
    ]);
    $withOtherCalls = new AgentContext(prismCalls: [
        ['method' => 'withMaxSteps', 'args' => [10]],
    ]);

    expect($empty->hasSchemaCall())->toBeFalse();
    expect($withSchema->hasSchemaCall())->toBeTrue();
    expect($withOtherCalls->hasSchemaCall())->toBeFalse();
});

test('it gets schema from prism calls', function () {
    $mockSchema = new \stdClass;
    $mockSchema->name = 'test-schema';

    $contextWithSchema = new AgentContext(prismCalls: [
        ['method' => 'withMaxSteps', 'args' => [10]],
        ['method' => 'withSchema', 'args' => [$mockSchema]],
    ]);

    $contextWithoutSchema = new AgentContext(prismCalls: [
        ['method' => 'withMaxSteps', 'args' => [10]],
    ]);

    expect($contextWithSchema->getSchemaFromCalls())->toBe($mockSchema);
    expect($contextWithoutSchema->getSchemaFromCalls())->toBeNull();
});

test('it gets prism calls without schema', function () {
    $mockSchema = new \stdClass;

    $context = new AgentContext(prismCalls: [
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
    $context = new AgentContext;

    expect($context->getPrismCallsWithoutSchema())->toBe([]);
});

test('getPrismCallsWithoutSchema returns all calls when no schema', function () {
    $context = new AgentContext(prismCalls: [
        ['method' => 'withMaxSteps', 'args' => [10]],
        ['method' => 'usingTemperature', 'args' => [0.7]],
    ]);

    $calls = $context->getPrismCallsWithoutSchema();

    expect($calls)->toHaveCount(2);
    expect($calls[0]['method'])->toBe('withMaxSteps');
    expect($calls[1]['method'])->toBe('usingTemperature');
});

// === Runtime Tools Tests ===

test('it creates with default empty tools', function () {
    $context = new AgentContext;

    expect($context->tools)->toBe([]);
});

test('it creates with provided tools', function () {
    $context = new AgentContext(tools: ['App\\Tools\\MyTool']);

    expect($context->tools)->toBe(['App\\Tools\\MyTool']);
});

test('it reports hasTools correctly', function () {
    $empty = new AgentContext;
    $withTools = new AgentContext(tools: ['App\\Tools\\MyTool']);

    expect($empty->hasTools())->toBeFalse();
    expect($withTools->hasTools())->toBeTrue();
});

test('it creates with multiple tools', function () {
    $context = new AgentContext(tools: ['App\\Tools\\ToolA', 'App\\Tools\\ToolB']);

    expect($context->tools)->toHaveCount(2);
    expect($context->tools[0])->toBe('App\\Tools\\ToolA');
    expect($context->tools[1])->toBe('App\\Tools\\ToolB');
});

// === MCP Tools Tests ===

test('it creates with default empty mcpTools', function () {
    $context = new AgentContext;

    expect($context->mcpTools)->toBe([]);
});

test('it creates with provided mcpTools', function () {
    $mockTool = Mockery::mock(\Prism\Prism\Tool::class);

    $context = new AgentContext(mcpTools: [$mockTool]);

    expect($context->mcpTools)->toBe([$mockTool]);
});

test('it reports hasMcpTools correctly', function () {
    $mockTool = Mockery::mock(\Prism\Prism\Tool::class);

    $empty = new AgentContext;
    $withMcpTools = new AgentContext(mcpTools: [$mockTool]);

    expect($empty->hasMcpTools())->toBeFalse();
    expect($withMcpTools->hasMcpTools())->toBeTrue();
});

test('it creates with multiple mcpTools', function () {
    $mockTool1 = Mockery::mock(\Prism\Prism\Tool::class);
    $mockTool2 = Mockery::mock(\Prism\Prism\Tool::class);

    $context = new AgentContext(mcpTools: [$mockTool1, $mockTool2]);

    expect($context->mcpTools)->toHaveCount(2);
    expect($context->mcpTools[0])->toBe($mockTool1);
    expect($context->mcpTools[1])->toBe($mockTool2);
});

// === Variables Manipulation ===

test('withVariables replaces variables and returns new instance', function () {
    $context = new AgentContext(variables: ['a' => 1, 'b' => 2]);

    $newContext = $context->withVariables(['c' => 3]);

    expect($newContext)->not->toBe($context);
    expect($newContext->variables)->toBe(['c' => 3]);
    expect($context->variables)->toBe(['a' => 1, 'b' => 2]);
});

test('mergeVariables merges and returns new instance', function () {
    $context = new AgentContext(variables: ['a' => 1, 'b' => 2]);

    $newContext = $context->mergeVariables(['b' => 3, 'c' => 4]);

    expect($newContext)->not->toBe($context);
    expect($newContext->variables)->toBe(['a' => 1, 'b' => 3, 'c' => 4]);
    expect($context->variables)->toBe(['a' => 1, 'b' => 2]);
});

test('clearVariables removes all variables and returns new instance', function () {
    $context = new AgentContext(variables: ['a' => 1, 'b' => 2]);

    $newContext = $context->clearVariables();

    expect($newContext)->not->toBe($context);
    expect($newContext->variables)->toBe([]);
    expect($context->variables)->toBe(['a' => 1, 'b' => 2]);
});

// === Metadata Manipulation ===

test('withMetadata replaces metadata and returns new instance', function () {
    $context = new AgentContext(metadata: ['a' => 1, 'b' => 2]);

    $newContext = $context->withMetadata(['c' => 3]);

    expect($newContext)->not->toBe($context);
    expect($newContext->metadata)->toBe(['c' => 3]);
    expect($context->metadata)->toBe(['a' => 1, 'b' => 2]);
});

test('mergeMetadata merges and returns new instance', function () {
    $context = new AgentContext(metadata: ['a' => 1, 'b' => 2]);

    $newContext = $context->mergeMetadata(['b' => 3, 'c' => 4]);

    expect($newContext)->not->toBe($context);
    expect($newContext->metadata)->toBe(['a' => 1, 'b' => 3, 'c' => 4]);
    expect($context->metadata)->toBe(['a' => 1, 'b' => 2]);
});

test('clearMetadata removes all metadata and returns new instance', function () {
    $context = new AgentContext(metadata: ['a' => 1, 'b' => 2]);

    $newContext = $context->clearMetadata();

    expect($newContext)->not->toBe($context);
    expect($newContext->metadata)->toBe([]);
    expect($context->metadata)->toBe(['a' => 1, 'b' => 2]);
});

test('context manipulation preserves other properties', function () {
    $context = new AgentContext(
        messages: [['role' => 'user', 'content' => 'Hi']],
        variables: ['var' => 'value'],
        metadata: ['meta' => 'data'],
        providerOverride: 'anthropic',
        modelOverride: 'claude-3',
    );

    $newContext = $context->withVariables(['new' => 'var']);

    expect($newContext->messages)->toBe([['role' => 'user', 'content' => 'Hi']]);
    expect($newContext->metadata)->toBe(['meta' => 'data']);
    expect($newContext->providerOverride)->toBe('anthropic');
    expect($newContext->modelOverride)->toBe('claude-3');
});

// === Serialization Tests ===

test('toArray serializes all serializable properties', function () {
    $context = new AgentContext(
        messages: [['role' => 'user', 'content' => 'Hello']],
        variables: ['user_id' => 123],
        metadata: ['task_id' => 'abc'],
        providerOverride: 'anthropic',
        modelOverride: 'claude-3-opus',
        prismCalls: [['method' => 'withMaxSteps', 'args' => [10]]],
        tools: ['App\\Tools\\MyTool'],
    );

    $array = $context->toArray();

    expect($array)->toBe([
        'messages' => [['role' => 'user', 'content' => 'Hello']],
        'variables' => ['user_id' => 123],
        'metadata' => ['task_id' => 'abc'],
        'provider_override' => 'anthropic',
        'model_override' => 'claude-3-opus',
        'prism_calls' => [['method' => 'withMaxSteps', 'args' => [10]]],
        'tools' => ['App\\Tools\\MyTool'],
    ]);
});

test('fromArray restores context from array', function () {
    $data = [
        'messages' => [['role' => 'assistant', 'content' => 'Hi there']],
        'variables' => ['name' => 'John'],
        'metadata' => ['session' => 'xyz'],
        'provider_override' => 'openai',
        'model_override' => 'gpt-4o',
        'prism_calls' => [['method' => 'usingTemperature', 'args' => [0.7]]],
        'tools' => ['App\\Tools\\ToolA', 'App\\Tools\\ToolB'],
    ];

    $context = AgentContext::fromArray($data);

    expect($context->messages)->toBe([['role' => 'assistant', 'content' => 'Hi there']]);
    expect($context->variables)->toBe(['name' => 'John']);
    expect($context->metadata)->toBe(['session' => 'xyz']);
    expect($context->providerOverride)->toBe('openai');
    expect($context->modelOverride)->toBe('gpt-4o');
    expect($context->prismCalls)->toBe([['method' => 'usingTemperature', 'args' => [0.7]]]);
    expect($context->tools)->toBe(['App\\Tools\\ToolA', 'App\\Tools\\ToolB']);
});

test('toArray and fromArray round-trip preserves data', function () {
    $original = new AgentContext(
        messages: [
            ['role' => 'user', 'content' => 'First message'],
            ['role' => 'assistant', 'content' => 'Response'],
        ],
        variables: ['company' => 'Acme', 'tier' => 'premium'],
        metadata: ['request_id' => 'req-123', 'user_id' => 456],
        providerOverride: 'anthropic',
        modelOverride: 'claude-sonnet-4-20250514',
        prismCalls: [
            ['method' => 'withMaxSteps', 'args' => [5]],
            ['method' => 'usingTemperature', 'args' => [0.5]],
        ],
        tools: ['App\\Tools\\SearchTool', 'App\\Tools\\CalculateTool'],
    );

    $restored = AgentContext::fromArray($original->toArray());

    expect($restored->messages)->toBe($original->messages);
    expect($restored->variables)->toBe($original->variables);
    expect($restored->metadata)->toBe($original->metadata);
    expect($restored->providerOverride)->toBe($original->providerOverride);
    expect($restored->modelOverride)->toBe($original->modelOverride);
    expect($restored->prismCalls)->toBe($original->prismCalls);
    expect($restored->tools)->toBe($original->tools);
});

test('fromArray handles missing keys with defaults', function () {
    $context = AgentContext::fromArray([]);

    expect($context->messages)->toBe([]);
    expect($context->variables)->toBe([]);
    expect($context->metadata)->toBe([]);
    expect($context->providerOverride)->toBeNull();
    expect($context->modelOverride)->toBeNull();
    expect($context->prismCalls)->toBe([]);
    expect($context->tools)->toBe([]);
});

test('fromArray sets non-serializable properties to empty arrays', function () {
    $data = [
        'messages' => [['role' => 'user', 'content' => 'Test']],
        'variables' => ['key' => 'value'],
    ];

    $context = AgentContext::fromArray($data);

    expect($context->prismMedia)->toBe([]);
    expect($context->prismMessages)->toBe([]);
    expect($context->mcpTools)->toBe([]);
});

test('toArray excludes runtime-only properties', function () {
    $mockImage = Mockery::mock(\Prism\Prism\ValueObjects\Media\Image::class);
    $mockMessage = Mockery::mock(\Prism\Prism\ValueObjects\Messages\UserMessage::class);
    $mockTool = Mockery::mock(\Prism\Prism\Tool::class);

    $context = new AgentContext(
        messages: [['role' => 'user', 'content' => 'Hello']],
        variables: ['var' => 'value'],
        prismMedia: [$mockImage],
        prismMessages: [$mockMessage],
        mcpTools: [$mockTool],
    );

    $array = $context->toArray();

    expect($array)->not->toHaveKey('prism_media');
    expect($array)->not->toHaveKey('prism_messages');
    expect($array)->not->toHaveKey('mcp_tools');
    expect(array_keys($array))->toBe([
        'messages',
        'variables',
        'metadata',
        'provider_override',
        'model_override',
        'prism_calls',
        'tools',
    ]);
});

test('fromArray partial data preserves specified values', function () {
    $data = [
        'messages' => [['role' => 'user', 'content' => 'Hello']],
        'provider_override' => 'anthropic',
        // Other keys intentionally missing
    ];

    $context = AgentContext::fromArray($data);

    expect($context->messages)->toBe([['role' => 'user', 'content' => 'Hello']]);
    expect($context->providerOverride)->toBe('anthropic');
    expect($context->variables)->toBe([]);
    expect($context->metadata)->toBe([]);
    expect($context->modelOverride)->toBeNull();
});
