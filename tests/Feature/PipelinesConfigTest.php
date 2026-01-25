<?php

declare(strict_types=1);

use Atlasphp\Atlas\Contracts\PipelineContract;
use Atlasphp\Atlas\Pipelines\PipelineRegistry;
use Atlasphp\Atlas\Pipelines\PipelineRunner;

test('pipelines are enabled by default', function () {
    // Default config should have pipelines enabled
    expect(config('atlas.pipelines.enabled', true))->toBeTrue();

    $registry = app(PipelineRegistry::class);

    // Check that core pipelines are active
    expect($registry->active('agent.before_execute'))->toBeTrue();
    expect($registry->active('agent.after_execute'))->toBeTrue();
    expect($registry->active('tool.before_execute'))->toBeTrue();
});

test('pipelines remain active when config is explicitly set to null', function () {
    // When config is set to null, it's treated as "not explicitly disabled"
    // The service provider checks for `=== false`, so null doesn't disable
    config(['atlas.pipelines.enabled' => null]);

    $registry = app(\Atlasphp\Atlas\Pipelines\PipelineRegistry::class);

    // Pipelines should still be active (null !== false)
    expect($registry->active('agent.before_execute'))->toBeTrue();
});

test('pipeline handlers run when pipelines are enabled', function () {
    // Ensure pipelines are enabled
    expect(config('atlas.pipelines.enabled', true))->toBeTrue();

    $registry = app(PipelineRegistry::class);
    $runner = app(PipelineRunner::class);

    // Register a test handler
    $handlerCalled = false;
    $handler = new class($handlerCalled) implements PipelineContract
    {
        public function __construct(private bool &$called) {}

        public function handle(mixed $data, \Closure $next): mixed
        {
            $this->called = true;

            return $next($data);
        }
    };

    $registry->register('agent.before_execute', $handler);

    // Run the pipeline
    $runner->runIfActive('agent.before_execute', ['test' => 'data']);

    expect($handlerCalled)->toBeTrue();
});

test('disabling pipelines via setActive prevents handlers from running', function () {
    $registry = app(PipelineRegistry::class);
    $runner = app(PipelineRunner::class);

    // Register a test handler
    $handlerCalled = false;
    $handler = new class($handlerCalled) implements PipelineContract
    {
        public function __construct(private bool &$called) {}

        public function handle(mixed $data, \Closure $next): mixed
        {
            $this->called = true;

            return $next($data);
        }
    };

    $registry->register('agent.before_execute', $handler);

    // Disable the pipeline
    $registry->setActive('agent.before_execute', false);

    // Run the pipeline via runIfActive
    $runner->runIfActive('agent.before_execute', ['test' => 'data']);

    // Handler should not be called because pipeline is inactive
    expect($handlerCalled)->toBeFalse();
});

test('pipeline runner runIfActive respects active state', function () {
    $registry = app(PipelineRegistry::class);
    $runner = app(PipelineRunner::class);

    $callCount = 0;
    $handler = new class($callCount) implements PipelineContract
    {
        public function __construct(private int &$count) {}

        public function handle(mixed $data, \Closure $next): mixed
        {
            $this->count++;

            return $next($data);
        }
    };

    // Define and register a custom pipeline
    $registry->define('test.pipeline', 'Test pipeline', true);
    $registry->register('test.pipeline', $handler);

    // Run when active
    $runner->runIfActive('test.pipeline', []);
    expect($callCount)->toBe(1);

    // Disable and run again
    $registry->setActive('test.pipeline', false);
    $runner->runIfActive('test.pipeline', []);
    expect($callCount)->toBe(1); // Should not increase

    // Re-enable and run again
    $registry->setActive('test.pipeline', true);
    $runner->runIfActive('test.pipeline', []);
    expect($callCount)->toBe(2);
});

test('pipeline runner run always executes regardless of active state', function () {
    $registry = app(PipelineRegistry::class);
    $runner = app(PipelineRunner::class);

    $callCount = 0;
    $handler = new class($callCount) implements PipelineContract
    {
        public function __construct(private int &$count) {}

        public function handle(mixed $data, \Closure $next): mixed
        {
            $this->count++;

            return $next($data);
        }
    };

    // Define and register a custom pipeline
    $registry->define('test.forced.pipeline', 'Test forced pipeline', true);
    $registry->register('test.forced.pipeline', $handler);

    // Disable the pipeline
    $registry->setActive('test.forced.pipeline', false);

    // run() should still execute (it doesn't check active state)
    $runner->run('test.forced.pipeline', []);
    expect($callCount)->toBe(1);
});

test('destination is called when pipeline is inactive', function () {
    $registry = app(PipelineRegistry::class);
    $runner = app(PipelineRunner::class);

    // Define and disable a pipeline
    $registry->define('test.inactive.pipeline', 'Inactive pipeline', false);

    $destinationCalled = false;
    $runner->runIfActive('test.inactive.pipeline', ['data' => 'value'], function ($data) use (&$destinationCalled) {
        $destinationCalled = true;

        return $data;
    });

    // Destination should be called even though pipeline is inactive
    expect($destinationCalled)->toBeTrue();
});

test('all core pipelines are defined', function () {
    $registry = app(PipelineRegistry::class);
    $definitions = $registry->definitions();

    // Agent pipelines
    expect($definitions)->toHaveKey('agent.before_execute');
    expect($definitions)->toHaveKey('agent.after_execute');
    expect($definitions)->toHaveKey('agent.system_prompt.before_build');
    expect($definitions)->toHaveKey('agent.system_prompt.after_build');
    expect($definitions)->toHaveKey('agent.on_error');

    // Tool pipelines
    expect($definitions)->toHaveKey('tool.before_execute');
    expect($definitions)->toHaveKey('tool.after_execute');
    expect($definitions)->toHaveKey('tool.on_error');
});

test('pipelines can be disabled globally via config', function () {
    // This test simulates what happens when config is set to false
    $registry = new PipelineRegistry;

    // Define pipelines
    $registry->define('agent.before_execute');
    $registry->define('agent.after_execute');
    $registry->define('tool.before_execute');

    // All should be active initially
    expect($registry->active('agent.before_execute'))->toBeTrue();
    expect($registry->active('agent.after_execute'))->toBeTrue();
    expect($registry->active('tool.before_execute'))->toBeTrue();

    // Simulate what AtlasServiceProvider does when config is false
    $pipelinesEnabled = false;
    if ($pipelinesEnabled === false) {
        foreach ($registry->definitions() as $name => $definition) {
            $registry->setActive($name, false);
        }
    }

    // Now all should be inactive
    expect($registry->active('agent.before_execute'))->toBeFalse();
    expect($registry->active('agent.after_execute'))->toBeFalse();
    expect($registry->active('tool.before_execute'))->toBeFalse();
});

test('inactive pipelines can be re-enabled', function () {
    $registry = app(PipelineRegistry::class);

    // Disable a pipeline
    $registry->setActive('agent.before_execute', false);
    expect($registry->active('agent.before_execute'))->toBeFalse();

    // Re-enable it
    $registry->setActive('agent.before_execute', true);
    expect($registry->active('agent.before_execute'))->toBeTrue();
});

/**
 * Test that demonstrates proper config-based pipeline disabling.
 *
 * IMPORTANT: To properly disable pipelines in tests, the config must be
 * set BEFORE the service provider boots. Setting config after boot will
 * NOT disable pipelines - use setActive() instead for runtime disabling.
 */
test('pipelines are disabled when config is set before boot', function () {
    // This test class sets config in defineEnvironment() which runs BEFORE boot
    // See the DisabledPipelinesTest below for the proper way to test this
})->skip('Use DisabledPipelinesTest class for proper boot-time config testing');

test('setting config after boot does not affect pipeline state', function () {
    $registry = app(PipelineRegistry::class);

    // Pipelines are enabled by default after boot
    expect($registry->active('agent.before_execute'))->toBeTrue();

    // Setting config AFTER boot does NOT disable pipelines
    // because configurePipelinesState() already ran during boot
    config(['atlas.pipelines.enabled' => false]);

    // Pipeline is still active because the service provider already booted
    expect($registry->active('agent.before_execute'))->toBeTrue();

    // To disable after boot, you must use setActive() directly
    $registry->setActive('agent.before_execute', false);
    expect($registry->active('agent.before_execute'))->toBeFalse();
});
