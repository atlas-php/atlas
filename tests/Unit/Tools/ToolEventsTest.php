<?php

declare(strict_types=1);

use Atlasphp\Atlas\Tests\Fixtures\TestTool;
use Atlasphp\Atlas\Tools\Events\ToolExecuted;
use Atlasphp\Atlas\Tools\Events\ToolExecuting;
use Atlasphp\Atlas\Tools\Support\ToolContext;
use Atlasphp\Atlas\Tools\Support\ToolResult;

test('ToolExecuting holds tool, params, and context', function () {
    $tool = new TestTool;
    $context = new ToolContext;
    $params = ['input' => 'hello'];
    $event = new ToolExecuting($tool, $params, $context);

    expect($event->tool)->toBe($tool);
    expect($event->params)->toBe($params);
    expect($event->context)->toBe($context);
});

test('ToolExecuted holds tool, params, context, result, and duration', function () {
    $tool = new TestTool;
    $context = new ToolContext;
    $params = ['input' => 'hello'];
    $result = ToolResult::text('Result: hello');
    $event = new ToolExecuted($tool, $params, $context, $result, 42.5);

    expect($event->tool)->toBe($tool);
    expect($event->params)->toBe($params);
    expect($event->context)->toBe($context);
    expect($event->result)->toBe($result);
    expect($event->duration)->toBe(42.5);
});
