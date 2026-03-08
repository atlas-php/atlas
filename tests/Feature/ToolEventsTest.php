<?php

declare(strict_types=1);

use Atlasphp\Atlas\Tests\Fixtures\TestTool;
use Atlasphp\Atlas\Tools\Events\ToolExecuted;
use Atlasphp\Atlas\Tools\Events\ToolExecuting;
use Atlasphp\Atlas\Tools\Services\ToolExecutor;
use Atlasphp\Atlas\Tools\Support\ToolContext;
use Illuminate\Support\Facades\Event;

test('execute dispatches ToolExecuting and ToolExecuted events', function () {
    Event::fake([ToolExecuting::class, ToolExecuted::class]);

    $executor = app(ToolExecutor::class);
    $tool = new TestTool;
    $context = new ToolContext;

    $executor->execute($tool, ['input' => 'hello'], $context);

    Event::assertDispatched(ToolExecuting::class, function (ToolExecuting $event) {
        return $event->tool->name() === 'test_tool' && $event->params === ['input' => 'hello'];
    });

    Event::assertDispatched(ToolExecuted::class, function (ToolExecuted $event) {
        return $event->tool->name() === 'test_tool'
            && $event->result->toText() === 'Result: hello'
            && $event->duration >= 0;
    });
});

test('tool events are not dispatched when events.enabled is false', function () {
    Event::fake([ToolExecuting::class, ToolExecuted::class]);

    config(['atlas.events.enabled' => false]);

    $executor = app(ToolExecutor::class);
    $tool = new TestTool;
    $context = new ToolContext;

    $executor->execute($tool, ['input' => 'hello'], $context);

    Event::assertNotDispatched(ToolExecuting::class);
    Event::assertNotDispatched(ToolExecuted::class);
});
