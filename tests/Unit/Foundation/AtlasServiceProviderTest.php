<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Services\AgentExtensionRegistry;
use Atlasphp\Atlas\Contracts\Tools\Services\ToolExtensionRegistry;
use Atlasphp\Atlas\Pipelines\PipelineRegistry;
use Atlasphp\Atlas\Pipelines\PipelineRunner;

test('it registers AgentExtensionRegistry as singleton', function () {
    $instance1 = app(AgentExtensionRegistry::class);
    $instance2 = app(AgentExtensionRegistry::class);

    expect($instance1)->toBeInstanceOf(AgentExtensionRegistry::class);
    expect($instance1)->toBe($instance2);
});

test('it registers ToolExtensionRegistry as singleton', function () {
    $instance1 = app(ToolExtensionRegistry::class);
    $instance2 = app(ToolExtensionRegistry::class);

    expect($instance1)->toBeInstanceOf(ToolExtensionRegistry::class);
    expect($instance1)->toBe($instance2);
});

test('it registers PipelineRegistry as singleton', function () {
    $instance1 = app(PipelineRegistry::class);
    $instance2 = app(PipelineRegistry::class);

    expect($instance1)->toBeInstanceOf(PipelineRegistry::class);
    expect($instance1)->toBe($instance2);
});

test('it registers PipelineRunner as singleton', function () {
    $instance1 = app(PipelineRunner::class);
    $instance2 = app(PipelineRunner::class);

    expect($instance1)->toBeInstanceOf(PipelineRunner::class);
    expect($instance1)->toBe($instance2);
});
