<?php

declare(strict_types=1);

use Atlasphp\Atlas\Foundation\Contracts\PipelineContract;
use Atlasphp\Atlas\Foundation\Services\PipelineRegistry;

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

test('it can set active state for undefined pipeline', function () {
    $this->registry->setActive('new.pipeline', false);

    expect($this->registry->active('new.pipeline'))->toBeFalse();
    expect($this->registry->definitions())->toHaveKey('new.pipeline');
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
