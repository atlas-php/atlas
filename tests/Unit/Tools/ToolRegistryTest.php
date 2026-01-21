<?php

declare(strict_types=1);

use Atlasphp\Atlas\Tests\Fixtures\TestTool;
use Atlasphp\Atlas\Tools\Contracts\ToolContract;
use Atlasphp\Atlas\Tools\Exceptions\ToolException;
use Atlasphp\Atlas\Tools\Exceptions\ToolNotFoundException;
use Atlasphp\Atlas\Tools\Services\ToolRegistry;
use Illuminate\Container\Container;

beforeEach(function () {
    $this->container = new Container;
    $this->registry = new ToolRegistry($this->container);
});

test('it registers tool class', function () {
    $this->registry->register(TestTool::class);

    expect($this->registry->has('test_tool'))->toBeTrue();
});

test('it registers tool instance', function () {
    $tool = new TestTool;

    $this->registry->registerInstance($tool);

    expect($this->registry->has('test_tool'))->toBeTrue();
});

test('it throws on duplicate registration', function () {
    $this->registry->register(TestTool::class);

    $this->registry->register(TestTool::class);
})->throws(ToolException::class, "A tool with name 'test_tool' has already been registered.");

test('it allows override on duplicate registration', function () {
    $this->registry->register(TestTool::class);
    $this->registry->register(TestTool::class, override: true);

    expect($this->registry->has('test_tool'))->toBeTrue();
});

test('it gets tool by name', function () {
    $this->registry->register(TestTool::class);

    $tool = $this->registry->get('test_tool');

    expect($tool)->toBeInstanceOf(ToolContract::class);
    expect($tool->name())->toBe('test_tool');
});

test('it throws when getting unknown tool', function () {
    $this->registry->get('nonexistent');
})->throws(ToolNotFoundException::class, "No tool found with name 'nonexistent'.");

test('it reports has correctly', function () {
    expect($this->registry->has('test_tool'))->toBeFalse();

    $this->registry->register(TestTool::class);

    expect($this->registry->has('test_tool'))->toBeTrue();
});

test('it returns all tools', function () {
    $this->registry->register(TestTool::class);

    $all = $this->registry->all();

    expect($all)->toHaveKey('test_tool');
    expect($all['test_tool'])->toBeInstanceOf(ToolContract::class);
});

test('it returns only specified tools', function () {
    $this->registry->register(TestTool::class);

    $only = $this->registry->only(['test_tool']);

    expect($only)->toHaveKey('test_tool');
});

test('it returns only existing tools from filter', function () {
    $this->registry->register(TestTool::class);

    $only = $this->registry->only(['test_tool', 'nonexistent']);

    expect($only)->toHaveKey('test_tool');
    expect($only)->not->toHaveKey('nonexistent');
});

test('it returns all names', function () {
    $this->registry->register(TestTool::class);

    $names = $this->registry->names();

    expect($names)->toBe(['test_tool']);
});

test('it unregisters tool', function () {
    $this->registry->register(TestTool::class);

    $result = $this->registry->unregister('test_tool');

    expect($result)->toBeTrue();
    expect($this->registry->has('test_tool'))->toBeFalse();
});

test('it returns false when unregistering unknown tool', function () {
    $result = $this->registry->unregister('nonexistent');

    expect($result)->toBeFalse();
});

test('it counts registered tools', function () {
    expect($this->registry->count())->toBe(0);

    $this->registry->register(TestTool::class);

    expect($this->registry->count())->toBe(1);
});

test('it clears all tools', function () {
    $this->registry->register(TestTool::class);
    $this->registry->clear();

    expect($this->registry->count())->toBe(0);
});
