<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Agents\Support\PendingAgentRequest;
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\AtlasManager;
use Atlasphp\Atlas\PrismProxy;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\ValueObjects\Usage;

test('facade resolves atlas manager', function () {
    expect(Atlas::getFacadeRoot())->toBeInstanceOf(AtlasManager::class);
});

test('agent returns pending agent request', function () {
    expect(Atlas::agent('test-agent'))->toBeInstanceOf(PendingAgentRequest::class);
});

test('it executes simple chat', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('Hello from agent')
            ->withUsage(new Usage(10, 5)),
    ]);

    // Register the agent
    $registry = app(AgentRegistryContract::class);
    $registry->register(TestAgent::class);

    $response = Atlas::agent('test-agent')->chat('Hello');

    expect($response)->toBeInstanceOf(AgentResponse::class);
    expect($response->text)->toBe('Hello from agent');
});

test('it executes chat with messages', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('Continuing conversation')
            ->withUsage(new Usage(15, 10)),
    ]);

    $registry = app(AgentRegistryContract::class);
    $registry->register(TestAgent::class);

    $messages = [
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi there!'],
    ];

    $response = Atlas::agent('test-agent')
        ->withMessages($messages)
        ->chat('Continue');

    expect($response)->toBeInstanceOf(AgentResponse::class);
    expect($response->text)->toBe('Continuing conversation');
});

test('it executes chat with variables', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('Hello John')
            ->withUsage(new Usage(10, 5)),
    ]);

    $registry = app(AgentRegistryContract::class);
    $registry->register(TestAgent::class);

    $response = Atlas::agent('test-agent')
        ->withMessages([['role' => 'user', 'content' => 'Hello']])
        ->withVariables(['user_name' => 'John'])
        ->chat('Continue');

    expect($response)->toBeInstanceOf(AgentResponse::class);
    expect($response->text)->toBe('Hello John');
});

test('it executes chat with metadata', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('Response with metadata')
            ->withUsage(new Usage(10, 5)),
    ]);

    $registry = app(AgentRegistryContract::class);
    $registry->register(TestAgent::class);

    $response = Atlas::agent('test-agent')
        ->withMessages([['role' => 'user', 'content' => 'Hello']])
        ->withMetadata(['session_id' => 'abc123'])
        ->chat('Continue');

    expect($response)->toBeInstanceOf(AgentResponse::class);
    expect($response->text)->toBe('Response with metadata');
});

test('make returns pending agent request for anonymous agent', function () {
    expect(Atlas::make('You are a test assistant.'))
        ->toBeInstanceOf(PendingAgentRequest::class);
});

test('make executes chat with anonymous agent', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('Anonymous response')
            ->withUsage(new Usage(10, 5)),
    ]);

    $response = Atlas::make(
        systemPrompt: 'You are a test assistant.',
        provider: 'openai',
        model: 'gpt-4o',
    )->chat('Hello');

    expect($response)->toBeInstanceOf(AgentResponse::class);
    expect($response->text)->toBe('Anonymous response');
    expect($response->agentKey())->toBe('anonymous');
});

test('make passes provider and model to anonymous agent', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('Configured response')
            ->withUsage(new Usage(10, 5)),
    ]);

    $response = Atlas::make(
        systemPrompt: 'You summarize text.',
        provider: 'openai',
        model: 'gpt-4o',
        key: 'summarizer',
    )->chat('Summarize this');

    expect($response)->toBeInstanceOf(AgentResponse::class);
    expect($response->text)->toBe('Configured response');
    expect($response->agentKey())->toBe('summarizer');
});

test('prism methods are proxied via PrismProxy', function () {
    // Atlas proxies unknown methods to Prism via PrismProxy
    $textProxy = Atlas::text();

    expect($textProxy)->toBeInstanceOf(PrismProxy::class);
});
