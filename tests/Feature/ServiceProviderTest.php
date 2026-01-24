<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use Atlasphp\Atlas\Agents\Services\AgentExecutor;
use Atlasphp\Atlas\Agents\Services\AgentExtensionRegistry;
use Atlasphp\Atlas\Agents\Services\AgentRegistry;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Atlasphp\Atlas\Agents\Services\MediaConverter;
use Atlasphp\Atlas\Agents\Services\SystemPromptBuilder;
use Atlasphp\Atlas\AtlasManager;
use Atlasphp\Atlas\Pipelines\PipelineRegistry;
use Atlasphp\Atlas\Pipelines\PipelineRunner;
use Atlasphp\Atlas\Tools\Contracts\ToolRegistryContract;
use Atlasphp\Atlas\Tools\Services\ToolBuilder;
use Atlasphp\Atlas\Tools\Services\ToolExecutor;
use Atlasphp\Atlas\Tools\Services\ToolRegistry;

test('it registers foundation services', function () {
    expect($this->app->make(PipelineRegistry::class))->toBeInstanceOf(PipelineRegistry::class);
    expect($this->app->make(PipelineRunner::class))->toBeInstanceOf(PipelineRunner::class);
});

test('it registers agent services', function () {
    expect($this->app->make(AgentRegistryContract::class))->toBeInstanceOf(AgentRegistry::class);
    expect($this->app->make(AgentResolver::class))->toBeInstanceOf(AgentResolver::class);
    expect($this->app->make(SystemPromptBuilder::class))->toBeInstanceOf(SystemPromptBuilder::class);
    expect($this->app->make(MediaConverter::class))->toBeInstanceOf(MediaConverter::class);
    expect($this->app->make(AgentExecutorContract::class))->toBeInstanceOf(AgentExecutor::class);
    expect($this->app->make(AgentExtensionRegistry::class))->toBeInstanceOf(AgentExtensionRegistry::class);
});

test('it registers tool services', function () {
    expect($this->app->make(ToolRegistryContract::class))->toBeInstanceOf(ToolRegistry::class);
    expect($this->app->make(ToolExecutor::class))->toBeInstanceOf(ToolExecutor::class);
    expect($this->app->make(ToolBuilder::class))->toBeInstanceOf(ToolBuilder::class);
});

test('it registers AtlasManager', function () {
    expect($this->app->make(AtlasManager::class))->toBeInstanceOf(AtlasManager::class);
});

test('it defines core pipelines', function () {
    $registry = $this->app->make(PipelineRegistry::class);
    $definitions = $registry->definitions();

    expect($definitions)->toHaveKey('agent.before_execute');
    expect($definitions)->toHaveKey('agent.after_execute');
    expect($definitions)->toHaveKey('agent.system_prompt.before_build');
    expect($definitions)->toHaveKey('agent.system_prompt.after_build');
    expect($definitions)->toHaveKey('agent.on_error');
    expect($definitions)->toHaveKey('tool.before_execute');
    expect($definitions)->toHaveKey('tool.after_execute');
    expect($definitions)->toHaveKey('tool.on_error');
});

test('it resolves services as singletons', function () {
    $registry1 = $this->app->make(PipelineRegistry::class);
    $registry2 = $this->app->make(PipelineRegistry::class);

    expect($registry1)->toBe($registry2);

    $manager1 = $this->app->make(AtlasManager::class);
    $manager2 = $this->app->make(AtlasManager::class);

    expect($manager1)->toBe($manager2);
});
