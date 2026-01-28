<?php

declare(strict_types=1);

use Atlasphp\Atlas\Contracts\PipelineContract;
use Atlasphp\Atlas\Pipelines\ConditionalPipelineHandler;
use Atlasphp\Atlas\Pipelines\PipelineRegistry;
use Illuminate\Container\Container;

beforeEach(function () {
    $this->registry = new PipelineRegistry;
});

test('it can define a pipeline', function () {
    $this->registry->define('test.pipeline', 'Test pipeline description');

    $definitions = $this->registry->definitions();

    expect($definitions)->toHaveKey('test.pipeline');
    expect($definitions['test.pipeline']['description'])->toBe('Test pipeline description');
    expect($definitions['test.pipeline']['active'])->toBeTrue();
});

test('it can define an inactive pipeline', function () {
    $this->registry->define('test.pipeline', 'Description', active: false);

    expect($this->registry->active('test.pipeline'))->toBeFalse();
});

test('it can register a handler class string', function () {
    $this->registry->register('test.pipeline', TestPipelineHandler::class);

    expect($this->registry->has('test.pipeline'))->toBeTrue();
    expect($this->registry->get('test.pipeline'))->toBe([TestPipelineHandler::class]);
});

test('it can register a handler instance', function () {
    $handler = new TestPipelineHandler;
    $this->registry->register('test.pipeline', $handler);

    expect($this->registry->has('test.pipeline'))->toBeTrue();
    expect($this->registry->get('test.pipeline'))->toBe([$handler]);
});

test('it returns handlers sorted by priority (highest first)', function () {
    $this->registry->register('test.pipeline', LowPriorityHandler::class, priority: 10);
    $this->registry->register('test.pipeline', HighPriorityHandler::class, priority: 100);
    $this->registry->register('test.pipeline', MediumPriorityHandler::class, priority: 50);

    $handlers = $this->registry->get('test.pipeline');

    expect($handlers)->toBe([
        HighPriorityHandler::class,
        MediumPriorityHandler::class,
        LowPriorityHandler::class,
    ]);
});

test('it returns empty array for undefined pipeline', function () {
    expect($this->registry->get('nonexistent'))->toBe([]);
});

test('it reports has correctly', function () {
    expect($this->registry->has('test.pipeline'))->toBeFalse();

    $this->registry->register('test.pipeline', TestPipelineHandler::class);

    expect($this->registry->has('test.pipeline'))->toBeTrue();
});

test('it reports active state for undefined pipelines as true', function () {
    expect($this->registry->active('undefined.pipeline'))->toBeTrue();
});

test('it can set active state', function () {
    $this->registry->define('test.pipeline');

    expect($this->registry->active('test.pipeline'))->toBeTrue();

    $this->registry->setActive('test.pipeline', false);

    expect($this->registry->active('test.pipeline'))->toBeFalse();
});

test('it throws when setting active state for undefined pipeline', function () {
    expect(fn () => $this->registry->setActive('new.pipeline', false))
        ->toThrow(\InvalidArgumentException::class, 'Cannot set active state for undefined pipeline');
});

test('it can set active state for pipeline with handlers but no definition', function () {
    // Register a handler without defining the pipeline
    $this->registry->register('handler.only.pipeline', TestPipelineHandler::class);

    // Should work because the pipeline has handlers
    $this->registry->setActive('handler.only.pipeline', false);

    expect($this->registry->active('handler.only.pipeline'))->toBeFalse();
    expect($this->registry->definitions())->toHaveKey('handler.only.pipeline');
});

test('it returns all pipeline names', function () {
    $this->registry->register('first.pipeline', TestPipelineHandler::class);
    $this->registry->register('second.pipeline', TestPipelineHandler::class);

    $pipelines = $this->registry->pipelines();

    expect($pipelines)->toContain('first.pipeline');
    expect($pipelines)->toContain('second.pipeline');
});

test('it supports method chaining', function () {
    $result = $this->registry
        ->define('test.pipeline', 'Description')
        ->register('test.pipeline', TestPipelineHandler::class)
        ->setActive('test.pipeline', true);

    expect($result)->toBeInstanceOf(PipelineRegistry::class);
});

test('it can set container', function () {
    $container = new Container;

    $result = $this->registry->setContainer($container);

    expect($result)->toBeInstanceOf(PipelineRegistry::class);
});

test('it can register conditional handler with registerWhen', function () {
    $condition = fn (array $data) => ($data['premium'] ?? false) === true;

    $this->registry->registerWhen('test.pipeline', TestPipelineHandler::class, $condition);

    expect($this->registry->has('test.pipeline'))->toBeTrue();

    $handlers = $this->registry->get('test.pipeline');
    expect($handlers)->toHaveCount(1);
    expect($handlers[0])->toBeInstanceOf(ConditionalPipelineHandler::class);
});

test('registerWhen respects priority', function () {
    $condition = fn ($data) => true;

    $this->registry->registerWhen('test.pipeline', TestPipelineHandler::class, $condition, priority: 100);
    $this->registry->register('test.pipeline', LowPriorityHandler::class, priority: 10);

    $handlers = $this->registry->get('test.pipeline');

    // Conditional handler has higher priority, should be first
    expect($handlers[0])->toBeInstanceOf(ConditionalPipelineHandler::class);
    expect($handlers[1])->toBe(LowPriorityHandler::class);
});

test('registerWhen returns self for chaining', function () {
    $condition = fn ($data) => true;

    $result = $this->registry->registerWhen('test.pipeline', TestPipelineHandler::class, $condition);

    expect($result)->toBe($this->registry);
});

test('setContainer returns self for chaining', function () {
    $container = new Container;

    $result = $this->registry->setContainer($container);

    expect($result)->toBe($this->registry);
});

// === getWithPriority Tests ===

test('getWithPriority returns empty array for undefined pipeline', function () {
    expect($this->registry->getWithPriority('nonexistent.pipeline'))->toBe([]);
});

test('getWithPriority returns handlers with priority info', function () {
    $this->registry->register('test.pipeline', LowPriorityHandler::class, priority: 10);
    $this->registry->register('test.pipeline', HighPriorityHandler::class, priority: 100);

    $handlers = $this->registry->getWithPriority('test.pipeline');

    expect($handlers)->toHaveCount(2);
    // Sorted by priority (highest first)
    expect($handlers[0]['handler'])->toBe(HighPriorityHandler::class);
    expect($handlers[0]['priority'])->toBe(100);
    expect($handlers[1]['handler'])->toBe(LowPriorityHandler::class);
    expect($handlers[1]['priority'])->toBe(10);
});

test('getWithPriority returns handler instances with priority', function () {
    $handler = new TestPipelineHandler;
    $this->registry->register('test.pipeline', $handler, priority: 50);

    $handlers = $this->registry->getWithPriority('test.pipeline');

    expect($handlers)->toHaveCount(1);
    expect($handlers[0]['handler'])->toBe($handler);
    expect($handlers[0]['priority'])->toBe(50);
});

// Test Handler Classes

class TestPipelineHandler implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        return $next($data);
    }
}

class HighPriorityHandler implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        return $next($data);
    }
}

class MediumPriorityHandler implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        return $next($data);
    }
}

class LowPriorityHandler implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        return $next($data);
    }
}
