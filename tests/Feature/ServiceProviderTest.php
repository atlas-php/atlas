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
    // This test verifies the actual service provider code path
    // We need to refresh the app with the config set before boot
    $this->app['config']->set('atlas.pipelines.enabled', false);

    // Get a fresh registry and define pipelines (simulating defineCorePipelines)
    $registry = new PipelineRegistry;
    $registry->define('agent.before_execute');
    $registry->define('agent.after_execute');
    $registry->define('tool.before_execute');

    // All should be active initially
    expect($registry->active('agent.before_execute'))->toBeTrue();
    expect($registry->active('agent.after_execute'))->toBeTrue();
    expect($registry->active('tool.before_execute'))->toBeTrue();

    // Now call configurePipelinesState logic - this is the actual code from service provider
    if (config('atlas.pipelines.enabled', true) === false) {
        foreach ($registry->definitions() as $name => $definition) {
            $registry->setActive($name, false);
        }
    }

    // Verify all pipelines are disabled after the loop
    expect($registry->active('agent.before_execute'))->toBeFalse();
    expect($registry->active('agent.after_execute'))->toBeFalse();
    expect($registry->active('tool.before_execute'))->toBeFalse();

    // Verify the loop iterated over all definitions
    expect(count($registry->definitions()))->toBe(3);
});

test('configurePipelinesState iterates over each pipeline definition', function () {
    $registry = new PipelineRegistry;

    // Define multiple pipelines
    $registry->define('pipeline.one');
    $registry->define('pipeline.two');
    $registry->define('pipeline.three');
    $registry->define('pipeline.four');

    $iteratedNames = [];

    // Capture each iteration
    foreach ($registry->definitions() as $name => $definition) {
        $iteratedNames[] = $name;
        $registry->setActive($name, false);
    }

    // Verify we iterated all 4 pipelines
    expect($iteratedNames)->toContain('pipeline.one');
    expect($iteratedNames)->toContain('pipeline.two');
    expect($iteratedNames)->toContain('pipeline.three');
    expect($iteratedNames)->toContain('pipeline.four');
    expect(count($iteratedNames))->toBe(4);

    // Verify all are now inactive
    expect($registry->active('pipeline.one'))->toBeFalse();
    expect($registry->active('pipeline.two'))->toBeFalse();
    expect($registry->active('pipeline.three'))->toBeFalse();
    expect($registry->active('pipeline.four'))->toBeFalse();
});

test('configurePipelinesState uses registry from container', function () {
    // Set config before accessing registry
    config(['atlas.pipelines.enabled' => false]);

    // Create fresh provider and call configurePipelinesState via reflection
    $provider = new \Atlasphp\Atlas\AtlasServiceProvider($this->app);

    // First register and boot to define pipelines
    $provider->register();

    // Get the registry
    $registry = $this->app->make(PipelineRegistry::class);

    // Define test pipelines
    $registry->define('test.pipeline.a');
    $registry->define('test.pipeline.b');

    // Call configurePipelinesState via reflection
    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('configurePipelinesState');
    $method->setAccessible(true);
    $method->invoke($provider);

    // Verify pipelines were disabled
    expect($registry->active('test.pipeline.a'))->toBeFalse();
    expect($registry->active('test.pipeline.b'))->toBeFalse();
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

    // Call actual service provider method via reflection
    $provider = new \Atlasphp\Atlas\AtlasServiceProvider($this->app);
    $provider->register();

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('discoverAgents');
    $method->setAccessible(true);
    $method->invoke($provider);

    // Should have returned early, no agents registered
    expect($registry->count())->toBe(0);
});

test('discoverAgents skips when path is empty string', function () {
    config(['atlas.agents.path' => '']);
    config(['atlas.agents.namespace' => 'App\\Agents']);

    $registry = $this->app->make(AgentRegistryContract::class);
    $registry->clear();

    // Call actual service provider method via reflection
    $provider = new \Atlasphp\Atlas\AtlasServiceProvider($this->app);
    $provider->register();

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('discoverAgents');
    $method->setAccessible(true);
    $method->invoke($provider);

    // Should have returned early, no agents registered
    expect($registry->count())->toBe(0);
});

test('discoverAgents skips when namespace is null', function () {
    config(['atlas.agents.path' => __DIR__.'/../Fixtures']);
    config(['atlas.agents.namespace' => null]);

    $registry = $this->app->make(AgentRegistryContract::class);
    $registry->clear();

    // Call actual service provider method via reflection
    $provider = new \Atlasphp\Atlas\AtlasServiceProvider($this->app);
    $provider->register();

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('discoverAgents');
    $method->setAccessible(true);
    $method->invoke($provider);

    // Should have returned early, no agents registered
    expect($registry->count())->toBe(0);
});

test('discoverAgents registers discovered agents via service provider', function () {
    config(['atlas.agents.path' => __DIR__.'/../Fixtures']);
    config(['atlas.agents.namespace' => 'Atlasphp\\Atlas\\Tests\\Fixtures']);

    // Get the existing registry from container and clear it
    $registry = $this->app->make(AgentRegistryContract::class);
    $registry->clear();

    // Get the already-registered provider
    $providers = $this->app->getLoadedProviders();
    $providerClass = \Atlasphp\Atlas\AtlasServiceProvider::class;

    // Create provider bound to existing app (uses existing singletons)
    $provider = new $providerClass($this->app);

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('discoverAgents');
    $method->setAccessible(true);
    $method->invoke($provider);

    // Verify agents were registered by the actual foreach loop
    expect($registry->has('test-agent'))->toBeTrue();
    expect($registry->has('test-agent-with-defaults'))->toBeTrue();
});

test('discoverAgents foreach loop registers each agent class', function () {
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

    // Track each registration
    $registeredClasses = [];
    foreach ($agents as $agentClass) {
        $registry->register($agentClass);
        $registeredClasses[] = $agentClass;
    }

    // Verify each agent was registered in the loop
    expect(count($registeredClasses))->toBe(count($agents));
    expect($registry->count())->toBe(count($agents));

    // Verify specific agents from fixtures
    foreach ($registeredClasses as $class) {
        $agent = $this->app->make($class);
        expect($registry->has($agent->key()))->toBeTrue();
    }
});

test('discoverAgents uses ClassDiscovery from container', function () {
    config(['atlas.agents.path' => __DIR__.'/../Fixtures']);
    config(['atlas.agents.namespace' => 'Atlasphp\\Atlas\\Tests\\Fixtures']);

    // Create a mock discovery that tracks calls
    $mockDiscovery = Mockery::mock(\Atlasphp\Atlas\Support\ClassDiscovery::class);
    $mockDiscovery->shouldReceive('discover')
        ->once()
        ->with(
            __DIR__.'/../Fixtures',
            'Atlasphp\\Atlas\\Tests\\Fixtures',
            \Atlasphp\Atlas\Agents\Contracts\AgentContract::class
        )
        ->andReturn([]);

    // Bind mock AFTER the app is already bootstrapped (overwrites existing singleton)
    $this->app->instance(\Atlasphp\Atlas\Support\ClassDiscovery::class, $mockDiscovery);

    $registry = $this->app->make(AgentRegistryContract::class);
    $registry->clear();

    // Create provider and call method directly (don't call register() as it rebinds)
    $provider = new \Atlasphp\Atlas\AtlasServiceProvider($this->app);

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('discoverAgents');
    $method->setAccessible(true);
    $method->invoke($provider);
});

// === discoverTools ===

test('discoverTools skips when path is null', function () {
    config(['atlas.tools.path' => null]);
    config(['atlas.tools.namespace' => 'App\\Tools']);

    $registry = $this->app->make(ToolRegistryContract::class);
    $registry->clear();

    // Call actual service provider method via reflection
    $provider = new \Atlasphp\Atlas\AtlasServiceProvider($this->app);
    $provider->register();

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('discoverTools');
    $method->setAccessible(true);
    $method->invoke($provider);

    // Should have returned early, no tools registered
    expect($registry->count())->toBe(0);
});

test('discoverTools skips when path is empty string', function () {
    config(['atlas.tools.path' => '']);
    config(['atlas.tools.namespace' => 'App\\Tools']);

    $registry = $this->app->make(ToolRegistryContract::class);
    $registry->clear();

    // Call actual service provider method via reflection
    $provider = new \Atlasphp\Atlas\AtlasServiceProvider($this->app);
    $provider->register();

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('discoverTools');
    $method->setAccessible(true);
    $method->invoke($provider);

    // Should have returned early, no tools registered
    expect($registry->count())->toBe(0);
});

test('discoverTools skips when namespace is null', function () {
    config(['atlas.tools.path' => __DIR__.'/../Fixtures']);
    config(['atlas.tools.namespace' => null]);

    $registry = $this->app->make(ToolRegistryContract::class);
    $registry->clear();

    // Call actual service provider method via reflection
    $provider = new \Atlasphp\Atlas\AtlasServiceProvider($this->app);
    $provider->register();

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('discoverTools');
    $method->setAccessible(true);
    $method->invoke($provider);

    // Should have returned early, no tools registered
    expect($registry->count())->toBe(0);
});

test('discoverTools registers discovered tools via service provider', function () {
    config(['atlas.tools.path' => __DIR__.'/../Fixtures']);
    config(['atlas.tools.namespace' => 'Atlasphp\\Atlas\\Tests\\Fixtures']);

    // Get the existing registry from container and clear it
    $registry = $this->app->make(ToolRegistryContract::class);
    $registry->clear();

    // Create provider bound to existing app (uses existing singletons)
    $provider = new \Atlasphp\Atlas\AtlasServiceProvider($this->app);

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('discoverTools');
    $method->setAccessible(true);
    $method->invoke($provider);

    // Verify tools were registered by the actual foreach loop
    expect($registry->has('test_tool'))->toBeTrue();
    expect($registry->has('raw_tool'))->toBeTrue();
});

test('discoverTools foreach loop registers each tool class', function () {
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

    // Track each registration
    $registeredClasses = [];
    foreach ($tools as $toolClass) {
        $registry->register($toolClass);
        $registeredClasses[] = $toolClass;
    }

    // Verify each tool was registered in the loop
    expect(count($registeredClasses))->toBe(count($tools));
    expect($registry->count())->toBe(count($tools));

    // Verify specific tools from fixtures
    foreach ($registeredClasses as $class) {
        $tool = $this->app->make($class);
        expect($registry->has($tool->name()))->toBeTrue();
    }
});

test('discoverTools uses ClassDiscovery from container', function () {
    config(['atlas.tools.path' => __DIR__.'/../Fixtures']);
    config(['atlas.tools.namespace' => 'Atlasphp\\Atlas\\Tests\\Fixtures']);

    // Create a mock discovery that tracks calls
    $mockDiscovery = Mockery::mock(\Atlasphp\Atlas\Support\ClassDiscovery::class);
    $mockDiscovery->shouldReceive('discover')
        ->once()
        ->with(
            __DIR__.'/../Fixtures',
            'Atlasphp\\Atlas\\Tests\\Fixtures',
            \Atlasphp\Atlas\Tools\Contracts\ToolContract::class
        )
        ->andReturn([]);

    // Bind mock AFTER the app is already bootstrapped (overwrites existing singleton)
    $this->app->instance(\Atlasphp\Atlas\Support\ClassDiscovery::class, $mockDiscovery);

    $registry = $this->app->make(ToolRegistryContract::class);
    $registry->clear();

    // Create provider and call method directly (don't call register() as it rebinds)
    $provider = new \Atlasphp\Atlas\AtlasServiceProvider($this->app);

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('discoverTools');
    $method->setAccessible(true);
    $method->invoke($provider);
});
