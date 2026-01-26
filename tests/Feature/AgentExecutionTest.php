<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use Atlasphp\Atlas\Agents\Services\AgentExecutor;
use Atlasphp\Atlas\Agents\Services\AgentRegistry;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Atlasphp\Atlas\Agents\Services\SystemPromptBuilder;
use Atlasphp\Atlas\Agents\Support\AgentContext;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\Text\Response as PrismResponse;
use Prism\Prism\ValueObjects\Usage;

test('it registers agent services in container', function () {
    expect(app(AgentRegistryContract::class))->toBeInstanceOf(AgentRegistry::class);
    expect(app(AgentExecutorContract::class))->toBeInstanceOf(AgentExecutor::class);
    expect(app(AgentResolver::class))->toBeInstanceOf(AgentResolver::class);
    expect(app(SystemPromptBuilder::class))->toBeInstanceOf(SystemPromptBuilder::class);
});

test('it resolves services as singletons', function () {
    $registry1 = app(AgentRegistryContract::class);
    $registry2 = app(AgentRegistryContract::class);

    expect($registry1)->toBe($registry2);
});

test('agent registry registers and retrieves agents', function () {
    $registry = app(AgentRegistryContract::class);

    $registry->register(TestAgent::class);

    expect($registry->has('test-agent'))->toBeTrue();
    expect($registry->get('test-agent'))->toBeInstanceOf(TestAgent::class);
});

test('agent resolver resolves from multiple sources', function () {
    $registry = app(AgentRegistryContract::class);
    $resolver = app(AgentResolver::class);

    // From instance
    $agent = new TestAgent;
    expect($resolver->resolve($agent))->toBe($agent);

    // From registry
    $registry->register(TestAgent::class);
    expect($resolver->resolve('test-agent'))->toBeInstanceOf(TestAgent::class);

    // From class
    expect($resolver->resolve(TestAgent::class))->toBeInstanceOf(TestAgent::class);
});

test('system prompt builder interpolates variables', function () {
    $builder = app(SystemPromptBuilder::class);
    $agent = new TestAgent;

    $context = new AgentContext(variables: [
        'agent_name' => 'Atlas',
        'user_name' => 'Jane',
    ]);

    $prompt = $builder->build($agent, $context);

    expect($prompt)->toContain('You are Atlas');
    expect($prompt)->toContain('Help Jane');
});

test('agent executor integrates with Prism', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('Mocked response')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = app(AgentExecutorContract::class);
    $agent = new TestAgent;
    $context = new AgentContext;

    $response = $executor->execute($agent, 'Hello', $context);

    expect($response)->toBeInstanceOf(PrismResponse::class);
    expect($response->text)->toBe('Mocked response');
});
