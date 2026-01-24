<?php

declare(strict_types=1);

namespace Atlasphp\Atlas;

use Atlasphp\Atlas\Pipelines\PipelineRunner;
use Generator;

/**
 * Dynamic proxy for Prism pending requests.
 *
 * Forwards all method calls to the underlying Prism request via __call().
 * Terminal methods (configured in $terminalMethods) are wrapped with
 * before/after pipeline hooks for observability. All other methods
 * pass through directly, enabling full Prism API access.
 *
 * @mixin \Prism\Prism\Text\PendingRequest
 * @mixin \Prism\Prism\Structured\PendingRequest
 * @mixin \Prism\Prism\Embeddings\PendingRequest
 * @mixin \Prism\Prism\Images\PendingRequest
 * @mixin \Prism\Prism\Audio\PendingRequest
 * @mixin \Prism\Prism\Moderation\PendingRequest
 */
final class PrismProxy
{
    /**
     * Terminal methods that should be wrapped with pipeline hooks.
     * Format: [module => [method => [before_event, after_event]]]
     *
     * @var array<string, array<string, array{0: string, 1: string}>>
     */
    protected static array $terminalMethods = [
        'text' => [
            'asText' => ['text.before_text', 'text.after_text'],
            'asStream' => ['text.before_stream', 'text.after_stream'],
        ],
        'structured' => [
            'asStructured' => ['structured.before_structured', 'structured.after_structured'],
        ],
        'embeddings' => [
            'asEmbeddings' => ['embeddings.before_embeddings', 'embeddings.after_embeddings'],
        ],
        'image' => [
            'generate' => ['image.before_generate', 'image.after_generate'],
        ],
        'audio' => [
            'asAudio' => ['audio.before_audio', 'audio.after_audio'],
            'asText' => ['audio.before_text', 'audio.after_text'],
        ],
        'moderation' => [
            'asModeration' => ['moderation.before_moderation', 'moderation.after_moderation'],
        ],
    ];

    /**
     * Methods that return generators and need special handling.
     *
     * @var array<int, string>
     */
    protected static array $generatorMethods = ['asStream'];

    /**
     * Metadata for pipeline middleware.
     *
     * @var array<string, mixed>
     */
    protected array $metadata = [];

    /**
     * The underlying Prism pending request.
     */
    protected object $pendingRequest;

    public function __construct(
        protected PipelineRunner $pipelineRunner,
        object $pendingRequest,
        protected string $module,
    ) {
        $this->pendingRequest = $pendingRequest;
    }

    /**
     * Get all pipeline event names for registration.
     *
     * @return array<int, string>
     */
    public static function getPipelineEvents(): array
    {
        $events = [];

        foreach (self::$terminalMethods as $methods) {
            foreach ($methods as [$beforeEvent, $afterEvent]) {
                $events[] = $beforeEvent;
                $events[] = $afterEvent;
            }
        }

        return $events;
    }

    /**
     * Add metadata for pipeline middleware.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): static
    {
        $clone = clone $this;
        $clone->metadata = array_merge($clone->metadata, $metadata);

        return $clone;
    }

    /**
     * Get the current metadata.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Handle all method calls dynamically.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        $hooks = self::$terminalMethods[$this->module][$method] ?? null;

        // Terminal method with pipeline hooks
        if ($hooks !== null) {
            return $this->executeWithPipelines($method, $arguments, $hooks);
        }

        // Regular fluent method - passthrough
        return $this->proxyToRequest($method, $arguments);
    }

    /**
     * Execute a terminal method wrapped with pipeline hooks.
     *
     * @param  array<int, mixed>  $arguments
     * @param  array{0: string, 1: string}  $hooks
     */
    protected function executeWithPipelines(string $method, array $arguments, array $hooks): mixed
    {
        [$beforeEvent, $afterEvent] = $hooks;

        // Handle generator methods specially
        if (in_array($method, self::$generatorMethods, true)) {
            return $this->executeGeneratorWithPipelines($method, $arguments, $beforeEvent);
        }

        // Run before pipeline
        $context = $this->buildContext();
        $context = $this->pipelineRunner->runIfActive($beforeEvent, $context);

        // Execute the method on the (possibly modified) request
        /** @var object $request */
        $request = $context['request'];
        $result = $request->{$method}(...$arguments);

        // Run after pipeline
        $context['response'] = $result;
        $context = $this->pipelineRunner->runIfActive($afterEvent, $context);

        return $context['response'];
    }

    /**
     * Execute a generator method with pipeline hooks.
     *
     * @param  array<int, mixed>  $arguments
     * @return Generator<mixed>
     */
    protected function executeGeneratorWithPipelines(
        string $method,
        array $arguments,
        string $beforeEvent,
    ): Generator {
        // Run before pipeline
        $context = $this->buildContext();
        $context = $this->pipelineRunner->runIfActive($beforeEvent, $context);

        // Yield from the generator
        /** @var object $request */
        $request = $context['request'];

        yield from $request->{$method}(...$arguments);
    }

    /**
     * Proxy a fluent method call to the underlying request.
     *
     * @param  array<int, mixed>  $arguments
     */
    protected function proxyToRequest(string $method, array $arguments): static
    {
        $result = $this->pendingRequest->{$method}(...$arguments);

        // If fluent pattern (returns object), wrap in clone
        if (is_object($result)) {
            $clone = clone $this;
            $clone->pendingRequest = $result;

            return $clone;
        }

        // Non-object return - shouldn't happen for fluent methods
        // but return self to maintain chain
        return $this;
    }

    /**
     * Build pipeline context.
     *
     * @return array{pipeline: string, metadata: array<string, mixed>, request: object}
     */
    protected function buildContext(): array
    {
        return [
            'pipeline' => $this->module,
            'metadata' => $this->metadata,
            'request' => $this->pendingRequest,
        ];
    }
}
