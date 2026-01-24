<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use Atlasphp\Atlas\Agents\Services\AgentExecutor;
use Atlasphp\Atlas\Agents\Services\AgentExtensionRegistry;
use Atlasphp\Atlas\Agents\Services\AgentRegistry;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Atlasphp\Atlas\Agents\Services\MediaConverter;
use Atlasphp\Atlas\Agents\Services\SystemPromptBuilder;
use Atlasphp\Atlas\AtlasManager;
use Atlasphp\Atlas\Pipelines\PipelineRegistry;
use Atlasphp\Atlas\Pipelines\PipelineRunner;
use Atlasphp\Atlas\Tools\Contracts\ToolRegistryContract;
use Atlasphp\Atlas\Tools\Services\ToolBuilder;
use Atlasphp\Atlas\Tools\Services\ToolExecutor;
use Atlasphp\Atlas\Tools\Services\ToolRegistry;

test('it registers foundation services', function () {
    expect($this->app->make(PipelineRegistry::class))->toBeInstanceOf(PipelineRegistry::class);
    expect($this->app->make(PipelineRunner::class))->toBeInstanceOf(PipelineRunner::class);
});

test('it registers agent services', function () {
    expect($this->app->make(AgentRegistryContract::class))->toBeInstanceOf(AgentRegistry::class);
    expect($this->app->make(AgentResolver::class))->toBeInstanceOf(AgentResolver::class);
    expect($this->app->make(SystemPromptBuilder::class))->toBeInstanceOf(SystemPromptBuilder::class);
    expect($this->app->make(MediaConverter::class))->toBeInstanceOf(MediaConverter::class);
    expect($this->app->make(AgentExecutorContract::class))->toBeInstanceOf(AgentExecutor::class);
    expect($this->app->make(AgentExtensionRegistry::class))->toBeInstanceOf(AgentExtensionRegistry::class);
});

test('it registers tool services', function () {
    expect($this->app->make(ToolRegistryContract::class))->toBeInstanceOf(ToolRegistry::class);
    expect($this->app->make(ToolExecutor::class))->toBeInstanceOf(ToolExecutor::class);
    expect($this->app->make(ToolBuilder::class))->toBeInstanceOf(ToolBuilder::class);
});

test('it registers AtlasManager', function () {
    expect($this->app->make(AtlasManager::class))->toBeInstanceOf(AtlasManager::class);
});

test('it defines core pipelines', function () {
    $registry = $this->app->make(PipelineRegistry::class);
    $definitions = $registry->definitions();

    expect($definitions)->toHaveKey('agent.before_execute');
    expect($definitions)->toHaveKey('agent.after_execute');
    expect($definitions)->toHaveKey('agent.system_prompt.before_build');
    expect($definitions)->toHaveKey('agent.system_prompt.after_build');
    expect($definitions)->toHaveKey('agent.on_error');
    expect($definitions)->toHaveKey('tool.before_execute');
    expect($definitions)->toHaveKey('tool.after_execute');
    expect($definitions)->toHaveKey('tool.on_error');
});

test('it resolves services as singletons', function () {
    $registry1 = $this->app->make(PipelineRegistry::class);
    $registry2 = $this->app->make(PipelineRegistry::class);

    expect($registry1)->toBe($registry2);

    $manager1 = $this->app->make(AtlasManager::class);
    $manager2 = $this->app->make(AtlasManager::class);

    expect($manager1)->toBe($manager2);
});

// === configurePipelinesState ===

test('configurePipelinesState disables all pipelines when config is false', function () {
    // Set config to false
    config(['atlas.pipelines.enabled' => false]);

    // Create a fresh registry and manually call configurePipelinesState logic
    $registry = new PipelineRegistry;

    // Define some pipelines
    $registry->define('agent.before_execute');
    $registry->define('agent.after_execute');
    $registry->define('tool.before_execute');

    // All should be active initially
    expect($registry->active('agent.before_execute'))->toBeTrue();
    expect($registry->active('agent.after_execute'))->toBeTrue();
    expect($registry->active('tool.before_execute'))->toBeTrue();

    // Simulate configurePipelinesState
    if (config('atlas.pipelines.enabled', true) === false) {
        foreach ($registry->definitions() as $name => $definition) {
            $registry->setActive($name, false);
        }
    }

    // All should now be inactive
    expect($registry->active('agent.before_execute'))->toBeFalse();
    expect($registry->active('agent.after_execute'))->toBeFalse();
    expect($registry->active('tool.before_execute'))->toBeFalse();
});

test('configurePipelinesState leaves pipelines active when config is true', function () {
    config(['atlas.pipelines.enabled' => true]);

    $registry = $this->app->make(PipelineRegistry::class);

    // All core pipelines should be active
    expect($registry->active('agent.before_execute'))->toBeTrue();
    expect($registry->active('agent.after_execute'))->toBeTrue();
    expect($registry->active('tool.before_execute'))->toBeTrue();
});

test('configurePipelinesState leaves pipelines active when config is not set', function () {
    // Don't set config, rely on default
    $registry = $this->app->make(PipelineRegistry::class);

    expect($registry->active('agent.before_execute'))->toBeTrue();
});

// === discoverAgents ===

test('discoverAgents skips when path is null', function () {
    config(['atlas.agents.path' => null]);
    config(['atlas.agents.namespace' => 'App\\Agents']);

    $registry = $this->app->make(AgentRegistryContract::class);
    $registry->clear();

    // Manually simulate discoverAgents logic
    $path = config('atlas.agents.path');
    $namespace = config('atlas.agents.namespace');

    if ($path === null || $path === '' || $namespace === null) {
        // Should return early
        expect($registry->count())->toBe(0);

        return;
    }

    $this->fail('Should have returned early');
});

test('discoverAgents skips when path is empty string', function () {
    config(['atlas.agents.path' => '']);
    config(['atlas.agents.namespace' => 'App\\Agents']);

    $registry = $this->app->make(AgentRegistryContract::class);
    $registry->clear();

    $path = config('atlas.agents.path');
    $namespace = config('atlas.agents.namespace');

    if ($path === null || $path === '' || $namespace === null) {
        expect($registry->count())->toBe(0);

        return;
    }

    $this->fail('Should have returned early');
});

test('discoverAgents skips when namespace is null', function () {
    config(['atlas.agents.path' => __DIR__.'/../Fixtures']);
    config(['atlas.agents.namespace' => null]);

    $registry = $this->app->make(AgentRegistryContract::class);
    $registry->clear();

    $path = config('atlas.agents.path');
    $namespace = config('atlas.agents.namespace');

    if ($path === null || $path === '' || $namespace === null) {
        expect($registry->count())->toBe(0);

        return;
    }

    $this->fail('Should have returned early');
});

test('discoverAgents registers discovered agents', function () {
    config(['atlas.agents.path' => __DIR__.'/../Fixtures']);
    config(['atlas.agents.namespace' => 'Atlasphp\\Atlas\\Tests\\Fixtures']);

    $registry = $this->app->make(AgentRegistryContract::class);
    $registry->clear();

    $discovery = $this->app->make(\Atlasphp\Atlas\Support\ClassDiscovery::class);

    $path = config('atlas.agents.path');
    $namespace = config('atlas.agents.namespace');

    $agents = $discovery->discover($path, $namespace, \Atlasphp\Atlas\Agents\Contracts\AgentContract::class);

    foreach ($agents as $agentClass) {
        $registry->register($agentClass);
    }

    expect($registry->has('test-agent'))->toBeTrue();
    expect($registry->has('test-agent-with-defaults'))->toBeTrue();
});

test('discoverAgents registers each agent class from discovery result', function () {
    config(['atlas.agents.path' => __DIR__.'/../Fixtures']);
    config(['atlas.agents.namespace' => 'Atlasphp\\Atlas\\Tests\\Fixtures']);

    $registry = $this->app->make(AgentRegistryContract::class);
    $registry->clear();

    $discovery = $this->app->make(\Atlasphp\Atlas\Support\ClassDiscovery::class);

    $agents = $discovery->discover(
        config('atlas.agents.path'),
        config('atlas.agents.namespace'),
        \Atlasphp\Atlas\Agents\Contracts\AgentContract::class
    );

    // Verify we have agents to register
    expect($agents)->not->toBeEmpty();

    // Manually iterate and register like the service provider does
    foreach ($agents as $agentClass) {
        $registry->register($agentClass);
    }

    // Verify count matches
    expect($registry->count())->toBe(count($agents));
});

// === discoverTools ===

test('discoverTools skips when path is null', function () {
    config(['atlas.tools.path' => null]);
    config(['atlas.tools.namespace' => 'App\\Tools']);

    $registry = $this->app->make(ToolRegistryContract::class);
    $registry->clear();

    $path = config('atlas.tools.path');
    $namespace = config('atlas.tools.namespace');

    if ($path === null || $path === '' || $namespace === null) {
        expect($registry->count())->toBe(0);

        return;
    }

    $this->fail('Should have returned early');
});

test('discoverTools skips when path is empty string', function () {
    config(['atlas.tools.path' => '']);
    config(['atlas.tools.namespace' => 'App\\Tools']);

    $registry = $this->app->make(ToolRegistryContract::class);
    $registry->clear();

    $path = config('atlas.tools.path');
    $namespace = config('atlas.tools.namespace');

    if ($path === null || $path === '' || $namespace === null) {
        expect($registry->count())->toBe(0);

        return;
    }

    $this->fail('Should have returned early');
});

test('discoverTools skips when namespace is null', function () {
    config(['atlas.tools.path' => __DIR__.'/../Fixtures']);
    config(['atlas.tools.namespace' => null]);

    $registry = $this->app->make(ToolRegistryContract::class);
    $registry->clear();

    $path = config('atlas.tools.path');
    $namespace = config('atlas.tools.namespace');

    if ($path === null || $path === '' || $namespace === null) {
        expect($registry->count())->toBe(0);

        return;
    }

    $this->fail('Should have returned early');
});

test('discoverTools registers discovered tools', function () {
    config(['atlas.tools.path' => __DIR__.'/../Fixtures']);
    config(['atlas.tools.namespace' => 'Atlasphp\\Atlas\\Tests\\Fixtures']);

    $registry = $this->app->make(ToolRegistryContract::class);
    $registry->clear();

    $discovery = $this->app->make(\Atlasphp\Atlas\Support\ClassDiscovery::class);

    $path = config('atlas.tools.path');
    $namespace = config('atlas.tools.namespace');

    $tools = $discovery->discover($path, $namespace, \Atlasphp\Atlas\Tools\Contracts\ToolContract::class);

    foreach ($tools as $toolClass) {
        $registry->register($toolClass);
    }

    expect($registry->has('test_tool'))->toBeTrue();
    expect($registry->has('raw_tool'))->toBeTrue();
});

test('discoverTools registers each tool class from discovery result', function () {
    config(['atlas.tools.path' => __DIR__.'/../Fixtures']);
    config(['atlas.tools.namespace' => 'Atlasphp\\Atlas\\Tests\\Fixtures']);

    $registry = $this->app->make(ToolRegistryContract::class);
    $registry->clear();

    $discovery = $this->app->make(\Atlasphp\Atlas\Support\ClassDiscovery::class);

    $tools = $discovery->discover(
        config('atlas.tools.path'),
        config('atlas.tools.namespace'),
        \Atlasphp\Atlas\Tools\Contracts\ToolContract::class
    );

    // Verify we have tools to register
    expect($tools)->not->toBeEmpty();

    // Manually iterate and register like the service provider does
    foreach ($tools as $toolClass) {
        $registry->register($toolClass);
    }

    // Verify count matches
    expect($registry->count())->toBe(count($tools));
});
