<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pipelines;

use Atlasphp\Atlas\Contracts\PipelineContract;
use Closure;
use Illuminate\Contracts\Container\Container;

/**
 * Registry for managing pipeline definitions and handlers.
 *
 * Provides an open pipeline system for registering middleware handlers
 * with priority-based ordering and optional pipeline definitions.
 */
class PipelineRegistry
{
    /**
     * Container for resolving conditional handlers.
     */
    protected ?Container $container = null;

    /**
     * Pipeline definitions with metadata.
     *
     * @var array<string, array{description: string, active: bool}>
     */
    protected array $definitions = [];

    /**
     * Registered pipeline handlers keyed by pipeline name.
     *
     * @var array<string, array<int, array{handler: class-string<PipelineContract>|PipelineContract, priority: int}>>
     */
    protected array $handlers = [];

    /**
     * Define a pipeline with metadata.
     *
     * @param  string  $name  The pipeline name.
     * @param  string  $description  Human-readable description.
     * @param  bool  $active  Whether the pipeline is active by default.
     */
    public function define(string $name, string $description = '', bool $active = true): static
    {
        $this->definitions[$name] = [
            'description' => $description,
            'active' => $active,
        ];

        if (! isset($this->handlers[$name])) {
            $this->handlers[$name] = [];
        }

        return $this;
    }

    /**
     * Register a handler for a pipeline.
     *
     * @param  string  $name  The pipeline name.
     * @param  class-string<PipelineContract>|PipelineContract  $handler  The handler class or instance.
     * @param  int  $priority  Handler priority (higher runs first).
     */
    public function register(string $name, string|PipelineContract $handler, int $priority = 0): static
    {
        if (! isset($this->handlers[$name])) {
            $this->handlers[$name] = [];
        }

        $this->handlers[$name][] = [
            'handler' => $handler,
            'priority' => $priority,
        ];

        return $this;
    }

    /**
     * Register a handler that only executes when a condition is met.
     *
     * The condition callback receives the pipeline data and should return
     * a boolean. If true, the handler executes. If false, it's skipped.
     *
     * @param  string  $name  The pipeline name.
     * @param  class-string<PipelineContract>|PipelineContract  $handler  The handler class or instance.
     * @param  Closure(mixed): bool  $condition  Condition that determines if handler should run.
     * @param  int  $priority  Handler priority (higher runs first).
     */
    public function registerWhen(
        string $name,
        string|PipelineContract $handler,
        Closure $condition,
        int $priority = 0,
    ): static {
        $conditionalHandler = new ConditionalPipelineHandler(
            $handler,
            $condition,
            $this->container,
        );

        return $this->register($name, $conditionalHandler, $priority);
    }

    /**
     * Set the container for resolving conditional handlers.
     *
     * Required for registerWhen() to resolve class-string handlers.
     */
    public function setContainer(Container $container): static
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Get handlers for a pipeline, sorted by priority (highest first).
     *
     * @param  string  $name  The pipeline name.
     * @return array<int, class-string<PipelineContract>|PipelineContract>
     */
    public function get(string $name): array
    {
        if (! isset($this->handlers[$name])) {
            return [];
        }

        $handlers = $this->handlers[$name];

        usort($handlers, fn (array $a, array $b) => $b['priority'] <=> $a['priority']);

        return array_column($handlers, 'handler');
    }

    /**
     * Get handlers with priority info for merging with runtime handlers.
     *
     * Returns handlers sorted by priority (highest first), including
     * the priority value for each handler.
     *
     * @param  string  $name  The pipeline name.
     * @return array<int, array{handler: class-string<PipelineContract>|PipelineContract, priority: int}>
     */
    public function getWithPriority(string $name): array
    {
        if (! isset($this->handlers[$name])) {
            return [];
        }

        $handlers = $this->handlers[$name];

        usort($handlers, fn (array $a, array $b) => $b['priority'] <=> $a['priority']);

        return $handlers;
    }

    /**
     * Check if a pipeline has any registered handlers.
     *
     * @param  string  $name  The pipeline name.
     */
    public function has(string $name): bool
    {
        return isset($this->handlers[$name]) && $this->handlers[$name] !== [];
    }

    /**
     * Get all pipeline definitions.
     *
     * @return array<string, array{description: string, active: bool}>
     */
    public function definitions(): array
    {
        return $this->definitions;
    }

    /**
     * Check if a pipeline is active.
     *
     * @param  string  $name  The pipeline name.
     */
    public function active(string $name): bool
    {
        if (! isset($this->definitions[$name])) {
            // Pipelines without explicit definition are active by default
            return true;
        }

        return $this->definitions[$name]['active'];
    }

    /**
     * Set a pipeline's active state.
     *
     * This method only updates the active state for pipelines that have
     * been explicitly defined or have registered handlers. It will not
     * create implicit definitions for unknown pipelines.
     *
     * @param  string  $name  The pipeline name.
     * @param  bool  $active  The active state.
     *
     * @throws \InvalidArgumentException If the pipeline has not been defined and has no handlers.
     */
    public function setActive(string $name, bool $active): static
    {
        if (! isset($this->definitions[$name]) && ! isset($this->handlers[$name])) {
            throw new \InvalidArgumentException(
                sprintf('Cannot set active state for undefined pipeline: %s. Define the pipeline first using define().', $name)
            );
        }

        if (! isset($this->definitions[$name])) {
            // Pipeline has handlers but no definition - create minimal definition
            $this->definitions[$name] = [
                'description' => '',
                'active' => $active,
            ];
        } else {
            $this->definitions[$name]['active'] = $active;
        }

        return $this;
    }

    /**
     * Get all registered pipeline names.
     *
     * @return array<int, string>
     */
    public function pipelines(): array
    {
        return array_keys($this->handlers);
    }
}
