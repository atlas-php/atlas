<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Support;

use Atlasphp\Atlas\Contracts\PipelineContract;

/**
 * Trait for attaching runtime middleware to agent requests.
 *
 * Provides fluent methods for adding per-request pipeline handlers
 * that execute alongside global handlers. Uses the clone pattern
 * for immutability. Multiple calls accumulate handlers.
 */
trait HasMiddlewareSupport
{
    /**
     * Runtime middleware handlers keyed by pipeline event.
     *
     * @var array<string, array<int, array{handler: class-string<PipelineContract>|PipelineContract, priority: int}>>
     */
    private array $middleware = [];

    /**
     * Attach middleware handlers for pipeline events.
     *
     * Uses Laravel-style array-keyed syntax. Multiple calls accumulate handlers.
     * Each handler runs in registration order within its event, merged with
     * global handlers by priority.
     *
     * ```php
     * // Single handler per event
     * ->middleware([
     *     'agent.before_execute' => MyMiddleware::class,
     *     'agent.after_execute' => LoggingMiddleware::class,
     * ])
     *
     * // Multiple handlers for same event
     * ->middleware([
     *     'agent.before_execute' => [FirstHandler::class, SecondHandler::class],
     * ])
     *
     * // Handler instances instead of class-strings
     * ->middleware([
     *     'agent.after_execute' => new LoggingMiddleware($logger),
     * ])
     * ```
     *
     * @param  array<string, class-string<PipelineContract>|PipelineContract|array<class-string<PipelineContract>|PipelineContract>>  $middleware
     */
    public function middleware(array $middleware): static
    {
        $clone = clone $this;

        foreach ($middleware as $event => $handlers) {
            // Wrap single handler (string or object) in array, preserve arrays as-is
            $handlerList = is_array($handlers) ? $handlers : [$handlers];

            foreach ($handlerList as $handler) {
                $clone->middleware[$event][] = [
                    'handler' => $handler,
                    'priority' => 0,
                ];
            }
        }

        return $clone;
    }

    /**
     * Remove all runtime middleware.
     */
    public function withoutMiddleware(): static
    {
        $clone = clone $this;
        $clone->middleware = [];

        return $clone;
    }

    /**
     * Get the configured middleware.
     *
     * @return array<string, array<int, array{handler: class-string<PipelineContract>|PipelineContract, priority: int}>>
     */
    protected function getMiddleware(): array
    {
        return $this->middleware;
    }
}
