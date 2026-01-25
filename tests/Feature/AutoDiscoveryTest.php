<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use Atlasphp\Atlas\Support\ClassDiscovery;
use Atlasphp\Atlas\Tools\Contracts\ToolContract;
use Atlasphp\Atlas\Tools\Contracts\ToolRegistryContract;

test('ClassDiscovery is registered as singleton', function () {
    $instance1 = app(ClassDiscovery::class);
    $instance2 = app(ClassDiscovery::class);

    expect($instance1)->toBeInstanceOf(ClassDiscovery::class);
    expect($instance1)->toBe($instance2);
});

test('agents are auto-discovered from configured path', function () {
    // Set the config to use our test fixtures directory
    config([
        'atlas.agents.path' => __DIR__.'/../Fixtures',
        'atlas.agents.namespace' => 'Atlasphp\\Atlas\\Tests\\Fixtures',
    ]);

    // Re-run the discovery manually since boot() already ran
    $discovery = app(ClassDiscovery::class);
    $registry = app(AgentRegistryContract::class);

    // Clear any existing registrations
    $registry->clear();

    // Discover agents
    $agents = $discovery->discover(
        config('atlas.agents.path'),
        config('atlas.agents.namespace'),
        AgentContract::class,
    );

    foreach ($agents as $agentClass) {
        $registry->register($agentClass);
    }

    // Verify TestAgent was discovered and registered
    expect($registry->has('test-agent'))->toBeTrue();
    expect($registry->has('test-agent-with-defaults'))->toBeTrue();
});

test('tools are auto-discovered from configured path', function () {
    // Set the config to use our test fixtures directory
    config([
        'atlas.tools.path' => __DIR__.'/../Fixtures',
        'atlas.tools.namespace' => 'Atlasphp\\Atlas\\Tests\\Fixtures',
    ]);

    // Re-run the discovery manually since boot() already ran
    $discovery = app(ClassDiscovery::class);
    $registry = app(ToolRegistryContract::class);

    // Clear any existing registrations
    $registry->clear();

    // Discover tools
    $tools = $discovery->discover(
        config('atlas.tools.path'),
        config('atlas.tools.namespace'),
        ToolContract::class,
    );

    foreach ($tools as $toolClass) {
        $registry->register($toolClass);
    }

    // Verify TestTool was discovered and registered
    expect($registry->has('test_tool'))->toBeTrue();
    expect($registry->has('raw_tool'))->toBeTrue();
});

test('discovery does nothing when path config is null', function () {
    config(['atlas.agents.path' => null]);

    $discovery = app(ClassDiscovery::class);
    $registry = app(AgentRegistryContract::class);

    // Clear and attempt discovery
    $registry->clear();

    $path = config('atlas.agents.path');
    $namespace = config('atlas.agents.namespace');

    if ($path !== null && $path !== '' && $namespace !== null) {
        $agents = $discovery->discover($path, $namespace, AgentContract::class);
        foreach ($agents as $agentClass) {
            $registry->register($agentClass);
        }
    }

    // Should have no agents
    expect($registry->count())->toBe(0);
});

test('discovery does nothing when namespace config is null', function () {
    config([
        'atlas.tools.path' => __DIR__.'/../Fixtures',
        'atlas.tools.namespace' => null,
    ]);

    $discovery = app(ClassDiscovery::class);
    $registry = app(ToolRegistryContract::class);

    // Clear and attempt discovery
    $registry->clear();

    $path = config('atlas.tools.path');
    $namespace = config('atlas.tools.namespace');

    if ($path !== null && $path !== '' && $namespace !== null) {
        $tools = $discovery->discover($path, $namespace, ToolContract::class);
        foreach ($tools as $toolClass) {
            $registry->register($toolClass);
        }
    }

    // Should have no tools
    expect($registry->count())->toBe(0);
});

test('discovery handles non-existent directories gracefully', function () {
    config([
        'atlas.agents.path' => '/non/existent/path/that/does/not/exist',
        'atlas.agents.namespace' => 'NonExistent\\Namespace',
    ]);

    $discovery = app(ClassDiscovery::class);
    $registry = app(AgentRegistryContract::class);

    // Clear and attempt discovery
    $registry->clear();

    $agents = $discovery->discover(
        config('atlas.agents.path'),
        config('atlas.agents.namespace'),
        AgentContract::class,
    );

    foreach ($agents as $agentClass) {
        $registry->register($agentClass);
    }

    // Should handle gracefully with no agents
    expect($registry->count())->toBe(0);
});

test('discovered agents can be retrieved and used', function () {
    config([
        'atlas.agents.path' => __DIR__.'/../Fixtures',
        'atlas.agents.namespace' => 'Atlasphp\\Atlas\\Tests\\Fixtures',
    ]);

    $discovery = app(ClassDiscovery::class);
    $registry = app(AgentRegistryContract::class);

    // Clear and re-discover
    $registry->clear();

    $agents = $discovery->discover(
        config('atlas.agents.path'),
        config('atlas.agents.namespace'),
        AgentContract::class,
    );

    foreach ($agents as $agentClass) {
        $registry->register($agentClass);
    }

    // Get the discovered agent and verify it works
    $agent = $registry->get('test-agent');

    expect($agent->name())->toBe('Test Agent');
    expect($agent->provider())->toBe('openai');
    expect($agent->model())->toBe('gpt-4');
});

test('discovered tools can be retrieved and used', function () {
    config([
        'atlas.tools.path' => __DIR__.'/../Fixtures',
        'atlas.tools.namespace' => 'Atlasphp\\Atlas\\Tests\\Fixtures',
    ]);

    $discovery = app(ClassDiscovery::class);
    $registry = app(ToolRegistryContract::class);

    // Clear and re-discover
    $registry->clear();

    $tools = $discovery->discover(
        config('atlas.tools.path'),
        config('atlas.tools.namespace'),
        ToolContract::class,
    );

    foreach ($tools as $toolClass) {
        $registry->register($toolClass);
    }

    // Get the discovered tool and verify it works
    $tool = $registry->get('test_tool');

    expect($tool->name())->toBe('test_tool');
    expect($tool->description())->toBe('A test tool that echoes input back.');
});
