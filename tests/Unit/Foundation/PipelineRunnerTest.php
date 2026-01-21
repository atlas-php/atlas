<?php

declare(strict_types=1);

use Atlasphp\Atlas\Foundation\Contracts\PipelineContract;
use Atlasphp\Atlas\Foundation\Services\PipelineRegistry;
use Atlasphp\Atlas\Foundation\Services\PipelineRunner;
use Illuminate\Container\Container;

beforeEach(function () {
    $this->registry = new PipelineRegistry;
    $this->container = new Container;
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

// Test Handler Classes

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
