<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\AnonymousAgent;
use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Enums\AgentType;
use Prism\Prism\Contracts\Schema;
use Prism\Prism\Tool;

test('it implements AgentContract', function () {
    $agent = new AnonymousAgent;

    expect($agent)->toBeInstanceOf(AgentContract::class);
});

test('it uses default values when no arguments provided', function () {
    $agent = new AnonymousAgent;

    expect($agent->key())->toBe('anonymous');
    expect($agent->name())->toBe('Anonymous Agent');
    expect($agent->type())->toBe(AgentType::Api);
    expect($agent->provider())->toBeNull();
    expect($agent->model())->toBeNull();
    expect($agent->systemPrompt())->toBeNull();
    expect($agent->description())->toBeNull();
    expect($agent->tools())->toBe([]);
    expect($agent->providerTools())->toBe([]);
    expect($agent->mcpTools())->toBe([]);
    expect($agent->temperature())->toBeNull();
    expect($agent->maxTokens())->toBeNull();
    expect($agent->maxSteps())->toBeNull();
    expect($agent->clientOptions())->toBe([]);
    expect($agent->providerOptions())->toBe([]);
    expect($agent->schema())->toBeNull();
});

test('all constructor parameters map to correct methods', function () {
    $schema = Mockery::mock(Schema::class);
    $mcpTool = Mockery::mock(Tool::class);

    $agent = new AnonymousAgent(
        agentKey: 'test-agent',
        systemPromptText: 'You are a test assistant.',
        agentProvider: 'anthropic',
        agentModel: 'claude-sonnet-4-20250514',
        agentTools: ['App\\Tools\\SearchTool'],
        agentName: 'Test Agent',
        agentDescription: 'A test agent',
        agentTemperature: 0.5,
        agentMaxTokens: 1000,
        agentMaxSteps: 5,
        agentSchema: $schema,
        agentProviderTools: ['web_search'],
        agentMcpTools: [$mcpTool],
        agentClientOptions: ['timeout' => 30],
        agentProviderOptions: ['seed' => 42],
    );

    expect($agent->key())->toBe('test-agent');
    expect($agent->name())->toBe('Test Agent');
    expect($agent->type())->toBe(AgentType::Api);
    expect($agent->provider())->toBe('anthropic');
    expect($agent->model())->toBe('claude-sonnet-4-20250514');
    expect($agent->systemPrompt())->toBe('You are a test assistant.');
    expect($agent->description())->toBe('A test agent');
    expect($agent->tools())->toBe(['App\\Tools\\SearchTool']);
    expect($agent->providerTools())->toBe(['web_search']);
    expect($agent->mcpTools())->toHaveCount(1);
    expect($agent->mcpTools()[0])->toBe($mcpTool);
    expect($agent->temperature())->toBe(0.5);
    expect($agent->maxTokens())->toBe(1000);
    expect($agent->maxSteps())->toBe(5);
    expect($agent->clientOptions())->toBe(['timeout' => 30]);
    expect($agent->providerOptions())->toBe(['seed' => 42]);
    expect($agent->schema())->toBe($schema);
});

test('name falls back to default when not provided', function () {
    $agent = new AnonymousAgent(
        agentKey: 'custom-key',
        systemPromptText: 'Hello',
    );

    expect($agent->name())->toBe('Anonymous Agent');
});

test('name uses provided value when given', function () {
    $agent = new AnonymousAgent(
        agentName: 'My Custom Agent',
    );

    expect($agent->name())->toBe('My Custom Agent');
});

test('partial constructor arguments use defaults for unspecified values', function () {
    $agent = new AnonymousAgent(
        agentKey: 'partial',
        systemPromptText: 'Hello',
        agentProvider: 'openai',
    );

    expect($agent->key())->toBe('partial');
    expect($agent->systemPrompt())->toBe('Hello');
    expect($agent->provider())->toBe('openai');
    expect($agent->model())->toBeNull();
    expect($agent->tools())->toBe([]);
    expect($agent->temperature())->toBeNull();
});
