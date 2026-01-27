<?php

declare(strict_types=1);

use Atlasphp\Atlas\Contracts\PipelineContract;
use Atlasphp\Atlas\Pipelines\PipelineRegistry;
use Atlasphp\Atlas\Pipelines\PipelineRunner;
use Illuminate\Container\Container;

beforeEach(function () {
    $this->registry = new PipelineRegistry;
    $this->container = new Container;
    $this->registry->setContainer($this->container);
    $this->runner = new PipelineRunner($this->registry, $this->container);
});

test('it returns data unchanged when no handlers registered', function () {
    $data = ['key' => 'value'];

    $result = $this->runner->run('empty.pipeline', $data);

    expect($result)->toBe($data);
});

test('it executes destination when no handlers and destination provided', function () {
    $data = ['key' => 'value'];
    $destination = fn ($d) => array_merge($d, ['added' => 'by-destination']);

    $result = $this->runner->run('empty.pipeline', $data, $destination);

    expect($result)->toBe(['key' => 'value', 'added' => 'by-destination']);
});

test('it runs handlers in priority order', function () {
    $this->registry->register('test.pipeline', AppendingHandlerA::class, priority: 10);
    $this->registry->register('test.pipeline', AppendingHandlerB::class, priority: 20);

    $result = $this->runner->run('test.pipeline', ['order' => []]);

    // B has higher priority, so runs first, then A
    expect($result['order'])->toBe(['B', 'A']);
});

test('it resolves class string handlers from container', function () {
    $this->registry->register('test.pipeline', CountingHandler::class);

    $result = $this->runner->run('test.pipeline', ['count' => 0]);

    expect($result['count'])->toBe(1);
});

test('it uses handler instances directly', function () {
    $handler = new CountingHandler;
    $this->registry->register('test.pipeline', $handler);

    $result = $this->runner->run('test.pipeline', ['count' => 0]);

    expect($result['count'])->toBe(1);
});

test('it supports handler that stops pipeline', function () {
    $this->registry->register('test.pipeline', StoppingHandler::class, priority: 100);
    $this->registry->register('test.pipeline', CountingHandler::class, priority: 10);

    $result = $this->runner->run('test.pipeline', ['count' => 0, 'stopped' => false]);

    expect($result['stopped'])->toBeTrue();
    expect($result['count'])->toBe(0); // CountingHandler never ran
});

test('it calls destination after all handlers', function () {
    $this->registry->register('test.pipeline', CountingHandler::class);

    $destination = fn ($d) => array_merge($d, ['destination' => true]);

    $result = $this->runner->run('test.pipeline', ['count' => 0], $destination);

    expect($result['count'])->toBe(1);
    expect($result['destination'])->toBeTrue();
});

test('runIfActive skips inactive pipelines', function () {
    $this->registry->define('test.pipeline', 'Description', active: false);
    $this->registry->register('test.pipeline', CountingHandler::class);

    $result = $this->runner->runIfActive('test.pipeline', ['count' => 0]);

    expect($result['count'])->toBe(0); // Handler didn't run
});

test('runIfActive executes active pipelines', function () {
    $this->registry->define('test.pipeline', 'Description', active: true);
    $this->registry->register('test.pipeline', CountingHandler::class);

    $result = $this->runner->runIfActive('test.pipeline', ['count' => 0]);

    expect($result['count'])->toBe(1);
});

test('runIfActive calls destination for inactive pipeline', function () {
    $this->registry->define('test.pipeline', 'Description', active: false);
    $destination = fn ($d) => array_merge($d, ['destination' => true]);

    $result = $this->runner->runIfActive('test.pipeline', ['key' => 'value'], $destination);

    expect($result['destination'])->toBeTrue();
});

test('runIfActive treats undefined pipelines as active', function () {
    $this->registry->register('test.pipeline', CountingHandler::class);

    $result = $this->runner->runIfActive('test.pipeline', ['count' => 0]);

    expect($result['count'])->toBe(1);
});

test('it throws InvalidArgumentException when handler does not implement PipelineContract', function () {
    // Register a class that doesn't implement PipelineContract
    $this->registry->register('test.pipeline', InvalidHandler::class);

    $this->runner->run('test.pipeline', ['key' => 'value']);
})->throws(
    \InvalidArgumentException::class,
    'Pipeline handler for "test.pipeline" must implement '.PipelineContract::class.', got InvalidHandler.'
);

test('it throws InvalidArgumentException with correct type for non-object handler result', function () {
    // Create a mock container that returns a non-object
    $container = new class extends Container
    {
        public function make($abstract, array $parameters = []): mixed
        {
            if ($abstract === 'NonObjectHandler') {
                return 'not an object';
            }

            return parent::make($abstract, $parameters);
        }
    };

    $runner = new PipelineRunner($this->registry, $container);
    $this->registry->register('test.pipeline', 'NonObjectHandler');

    $runner->run('test.pipeline', ['key' => 'value']);
})->throws(
    \InvalidArgumentException::class,
    'Pipeline handler for "test.pipeline" must implement '.PipelineContract::class.', got string.'
);

// Test Handler Classes

class InvalidHandler
{
    // Does not implement PipelineContract
    public function handle(mixed $data, Closure $next): mixed
    {
        return $next($data);
    }
}

class CountingHandler implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        $data['count'] = ($data['count'] ?? 0) + 1;

        return $next($data);
    }
}

class StoppingHandler implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        $data['stopped'] = true;

        return $data; // Don't call $next()
    }
}

class AppendingHandlerA implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        $data['order'][] = 'A';

        return $next($data);
    }
}

class AppendingHandlerB implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        $data['order'][] = 'B';

        return $next($data);
    }
}

// Conditional Execution Tests

test('registerWhen executes handler when condition is true', function () {
    $condition = fn (array $data) => ($data['premium'] ?? false) === true;

    $this->registry->registerWhen('test.pipeline', CountingHandler::class, $condition);

    // Premium user - handler should run
    $result = $this->runner->run('test.pipeline', ['count' => 0, 'premium' => true]);

    expect($result['count'])->toBe(1);
});

test('registerWhen skips handler when condition is false', function () {
    $condition = fn (array $data) => ($data['premium'] ?? false) === true;

    $this->registry->registerWhen('test.pipeline', CountingHandler::class, $condition);

    // Non-premium user - handler should be skipped
    $result = $this->runner->run('test.pipeline', ['count' => 0, 'premium' => false]);

    expect($result['count'])->toBe(0);
});

test('registerWhen works with handler instances', function () {
    $condition = fn (array $data) => ($data['allowed'] ?? false) === true;
    $handler = new CountingHandler;

    $this->registry->registerWhen('test.pipeline', $handler, $condition);

    $result = $this->runner->run('test.pipeline', ['count' => 0, 'allowed' => true]);

    expect($result['count'])->toBe(1);
});

test('conditional handlers can be mixed with regular handlers', function () {
    // Regular handler - always runs
    $this->registry->register('test.pipeline', AppendingHandlerA::class, priority: 30);

    // Conditional handler - only runs for premium
    $condition = fn (array $data) => ($data['premium'] ?? false) === true;
    $this->registry->registerWhen('test.pipeline', AppendingHandlerB::class, $condition, priority: 20);

    // Premium user - both handlers run
    $premiumResult = $this->runner->run('test.pipeline', ['order' => [], 'premium' => true]);
    expect($premiumResult['order'])->toBe(['A', 'B']);

    // Non-premium user - only regular handler runs
    $normalResult = $this->runner->run('test.pipeline', ['order' => [], 'premium' => false]);
    expect($normalResult['order'])->toBe(['A']);
});

test('multiple conditional handlers with different conditions', function () {
    $premiumCondition = fn (array $data) => ($data['tier'] ?? '') === 'premium';
    $adminCondition = fn (array $data) => ($data['role'] ?? '') === 'admin';

    $this->registry->registerWhen('test.pipeline', AppendingHandlerA::class, $premiumCondition);
    $this->registry->registerWhen('test.pipeline', AppendingHandlerB::class, $adminCondition);

    // Premium user - only A runs
    $premiumResult = $this->runner->run('test.pipeline', ['order' => [], 'tier' => 'premium']);
    expect($premiumResult['order'])->toBe(['A']);

    // Admin user - only B runs
    $adminResult = $this->runner->run('test.pipeline', ['order' => [], 'role' => 'admin']);
    expect($adminResult['order'])->toBe(['B']);

    // Premium admin - both run
    $bothResult = $this->runner->run('test.pipeline', ['order' => [], 'tier' => 'premium', 'role' => 'admin']);
    expect($bothResult['order'])->toBe(['A', 'B']);

    // Regular user - neither runs
    $neitherResult = $this->runner->run('test.pipeline', ['order' => []]);
    expect($neitherResult['order'])->toBe([]);
});

test('conditional handler passes data to next handler unchanged when skipped', function () {
    $condition = fn (array $data) => false; // Always skip

    $this->registry->registerWhen('test.pipeline', CountingHandler::class, $condition);
    $this->registry->register('test.pipeline', AppendingHandlerA::class, priority: -10);

    $result = $this->runner->run('test.pipeline', ['count' => 0, 'order' => [], 'custom_key' => 'preserved']);

    expect($result['count'])->toBe(0); // CountingHandler was skipped
    expect($result['order'])->toBe(['A']); // AppendingHandlerA ran
    expect($result['custom_key'])->toBe('preserved'); // Data passed through
});
