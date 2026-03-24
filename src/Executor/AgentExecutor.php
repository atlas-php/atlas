<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Executor;

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Events\AgentCompleted;
use Atlasphp\Atlas\Events\AgentMaxStepsExceeded;
use Atlasphp\Atlas\Events\AgentStarted;
use Atlasphp\Atlas\Events\AgentStepCompleted;
use Atlasphp\Atlas\Events\AgentStepStarted;
use Atlasphp\Atlas\Events\AgentToolCallCompleted;
use Atlasphp\Atlas\Events\AgentToolCallFailed;
use Atlasphp\Atlas\Events\AgentToolCallStarted;
use Atlasphp\Atlas\Exceptions\MaxStepsExceededException;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Middleware\MiddlewareStack;
use Atlasphp\Atlas\Middleware\StepContext;
use Atlasphp\Atlas\Middleware\ToolContext;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Concurrency;
use Spatie\Fork\Fork;

/**
 * Orchestrates the tool call loop for agent execution.
 *
 * Calls the driver, executes any requested tools, appends results
 * to the conversation, and repeats until the model stops or the
 * step limit is reached. Supports step and tool middleware.
 */
class AgentExecutor
{
    public function __construct(
        protected readonly Driver $driver,
        protected readonly ToolExecutor $toolExecutor,
        protected readonly Dispatcher $events,
        protected readonly ?MiddlewareStack $middlewareStack = null,
    ) {}

    /**
     * Execute the agent loop.
     *
     * @param  array<string, mixed>  $meta
     *
     * @throws MaxStepsExceededException
     */
    public function execute(
        TextRequest $request,
        ?int $maxSteps,
        bool $concurrent = false,
        array $meta = [],
        ?string $agentKey = null,
        ?string $provider = null,
        ?string $model = null,
        ?string $traceId = null,
    ): ExecutorResult {
        $steps = [];
        $stepCount = 0;
        $accumulatedUsage = new Usage(0, 0);
        $allProviderToolCalls = [];
        $allAnnotations = [];
        $lastFinishReason = null;

        $this->events->dispatch(new AgentStarted(
            agentKey: $agentKey,
            maxSteps: $maxSteps,
            concurrent: $concurrent,
            provider: $provider,
            model: $model,
            traceId: $traceId,
        ));

        try {
            while (true) {
                $stepCount++;

                if ($maxSteps !== null && $stepCount > $maxSteps) {
                    $this->events->dispatch(new AgentMaxStepsExceeded($maxSteps, $steps, agentKey: $agentKey, provider: $provider, model: $model, traceId: $traceId));

                    throw new MaxStepsExceededException($maxSteps, $stepCount);
                }

                $this->events->dispatch(new AgentStepStarted($stepCount, agentKey: $agentKey, provider: $provider, model: $model, traceId: $traceId));

                $response = $this->dispatchStep($request, $stepCount, $steps, $accumulatedUsage, $meta, $agentKey);

                $lastFinishReason = $response->finishReason;

                $this->events->dispatch(new AgentStepCompleted(
                    stepNumber: $stepCount,
                    finishReason: $response->finishReason,
                    usage: $response->usage,
                    agentKey: $agentKey,
                    provider: $provider,
                    model: $model,
                    traceId: $traceId,
                ));

                // Accumulate provider tool calls and annotations across all steps
                $allProviderToolCalls = array_merge($allProviderToolCalls, $response->providerToolCalls);
                $allAnnotations = array_merge($allAnnotations, $response->annotations);

                if ($response->finishReason !== FinishReason::ToolCalls) {
                    $steps[] = new Step(
                        text: $response->text,
                        toolCalls: $response->toolCalls,
                        toolResults: [],
                        usage: $response->usage,
                    );

                    break;
                }

                $toolResults = $concurrent
                    ? $this->executeToolsConcurrently($response->toolCalls, $meta, $stepCount, $agentKey, $provider, $model, $traceId)
                    : $this->executeToolsSequentially($response->toolCalls, $meta, $stepCount, $agentKey, $provider, $model, $traceId);

                $step = new Step(
                    text: $response->text,
                    toolCalls: $response->toolCalls,
                    toolResults: $toolResults,
                    usage: $response->usage,
                );

                $steps[] = $step;
                $accumulatedUsage = $accumulatedUsage->merge($step->usage);

                $messagesToAppend = [];

                // On the first tool loop iteration, move the user message into
                // the messages array so it appears BEFORE the assistant's tool
                // calls in conversation history. Without this, collectInputItems
                // appends the user message AFTER tool results, making the model
                // think there's a new user request after each tool completion.
                if ($request->message !== null) {
                    $messagesToAppend[] = new UserMessage(
                        $request->message,
                        $request->messageMedia,
                    );
                    $request = $request->withClearedMessage();
                }

                $messagesToAppend[] = $response->toMessage();

                foreach ($toolResults as $toolResult) {
                    $messagesToAppend[] = $toolResult->toMessage();
                }

                $request = $request->withAppendedMessages($messagesToAppend);
            }

            $totalUsage = $this->mergeUsage($steps);

            return new ExecutorResult(
                text: $response->text,
                reasoning: $response->reasoning,
                steps: $steps,
                usage: $totalUsage,
                finishReason: $response->finishReason,
                meta: $response->meta,
                providerToolCalls: $allProviderToolCalls,
                annotations: $allAnnotations,
            );
        } finally {
            $this->events->dispatch(new AgentCompleted(
                steps: $steps,
                usage: $totalUsage ?? $this->mergeUsage($steps),
                agentKey: $agentKey,
                finishReason: $lastFinishReason,
                provider: $provider,
                model: $model,
                traceId: $traceId,
            ));
        }
    }

    // ─── Step Dispatch ──────────────────────────────────────────────────

    /**
     * Dispatch a step through step middleware.
     *
     * @param  array<int, Step>  $previousSteps
     * @param  array<string, mixed>  $meta
     */
    protected function dispatchStep(
        TextRequest $request,
        int $stepNumber,
        array $previousSteps,
        Usage $accumulatedUsage,
        array $meta,
        ?string $agentKey = null,
    ): TextResponse {
        if ($this->middlewareStack === null) {
            return $this->driver->text($request);
        }

        $middleware = config('atlas.middleware.step', []);

        if ($middleware === []) {
            return $this->driver->text($request);
        }

        $context = new StepContext(
            stepNumber: $stepNumber,
            request: $request,
            accumulatedUsage: $accumulatedUsage,
            previousSteps: $previousSteps,
            meta: $meta,
            agentKey: $agentKey,
        );

        return $this->middlewareStack->run(
            $context,
            $middleware,
            fn (StepContext $ctx) => $this->driver->text($ctx->request),
        );
    }

    // ─── Tool Dispatch ──────────────────────────────────────────────────

    /**
     * Dispatch a tool execution through tool middleware.
     *
     * @param  array<string, mixed>  $meta
     */
    protected function dispatchTool(ToolCall $toolCall, array $meta, ?int $stepNumber = null, ?string $agentKey = null): ToolResult
    {
        if ($this->middlewareStack === null) {
            return $this->toolExecutor->execute($toolCall, $meta);
        }

        $middleware = config('atlas.middleware.tool', []);

        if ($middleware === []) {
            return $this->toolExecutor->execute($toolCall, $meta);
        }

        $context = new ToolContext(
            toolCall: $toolCall,
            meta: $meta,
            stepNumber: $stepNumber,
            agentKey: $agentKey,
        );

        return $this->middlewareStack->run(
            $context,
            $middleware,
            fn (ToolContext $ctx) => $this->toolExecutor->execute($ctx->toolCall, $ctx->meta),
        );
    }

    // ─── Sequential Execution ────────────────────────────────────────────

    /**
     * Execute tool calls one at a time.
     *
     * @param  array<int, ToolCall>  $toolCalls
     * @param  array<string, mixed>  $meta
     * @return array<int, ToolResult>
     */
    protected function executeToolsSequentially(array $toolCalls, array $meta, ?int $stepNumber = null, ?string $agentKey = null, ?string $provider = null, ?string $model = null, ?string $traceId = null): array
    {
        $results = [];

        foreach ($toolCalls as $toolCall) {
            $results[] = $this->executeSingleTool($toolCall, $meta, $stepNumber, $agentKey, $provider, $model, $traceId);
        }

        return $results;
    }

    // ─── Concurrent Execution ────────────────────────────────────────────

    /**
     * Execute tool calls concurrently using Laravel's Concurrency facade.
     *
     * Uses the fork driver when available (requires spatie/fork + pcntl)
     * for true parallelism. Falls back to the sync driver otherwise.
     * The default process driver is not used because it serializes closures
     * into child PHP processes, which fails with dependency-injected tools.
     *
     * Falls back to sequential if only one tool call is present.
     *
     * @param  array<int, ToolCall>  $toolCalls
     * @param  array<string, mixed>  $meta
     * @return array<int, ToolResult>
     */
    protected function executeToolsConcurrently(array $toolCalls, array $meta, ?int $stepNumber = null, ?string $agentKey = null, ?string $provider = null, ?string $model = null, ?string $traceId = null): array
    {
        if (count($toolCalls) === 1) {
            return $this->executeToolsSequentially($toolCalls, $meta, $stepNumber, $agentKey, $provider, $model, $traceId);
        }

        $toolCalls = array_values($toolCalls);

        // Fire AgentToolCallStarted events upfront for all tools
        foreach ($toolCalls as $toolCall) {
            $this->events->dispatch(new AgentToolCallStarted($toolCall, agentKey: $agentKey, stepNumber: $stepNumber, provider: $provider, model: $model, traceId: $traceId));
        }

        // Build closures that always return ToolResult — never Throwable.
        // This ensures return values serialize cleanly across fork boundaries.
        $tasks = array_map(
            fn (ToolCall $toolCall) => function () use ($toolCall, $meta, $stepNumber, $agentKey): ToolResult {
                try {
                    return $this->dispatchTool($toolCall, $meta, $stepNumber, $agentKey);
                } catch (\Throwable $e) {
                    return new ToolResult(
                        toolCall: $toolCall,
                        content: $e->getMessage(),
                        isError: true,
                    );
                }
            },
            $toolCalls,
        );

        /** @var array<int, ToolResult> $results */
        $results = Concurrency::driver($this->concurrencyDriver())->run($tasks);

        // Post-process: fire completion or error events
        foreach ($results as $result) {
            if ($result->isError) {
                $this->events->dispatch(new AgentToolCallFailed($result->toolCall, new \RuntimeException($result->content), agentKey: $agentKey, stepNumber: $stepNumber, provider: $provider, model: $model, traceId: $traceId));
            } else {
                $this->events->dispatch(new AgentToolCallCompleted($result->toolCall, $result, agentKey: $agentKey, stepNumber: $stepNumber, provider: $provider, model: $model, traceId: $traceId));
            }
        }

        return array_values($results);
    }

    /**
     * Resolve which concurrency driver to use.
     *
     * Prefers fork (true parallelism) when spatie/fork is installed and
     * pcntl is available. Falls back to sync (sequential via Concurrency
     * facade) otherwise. The default process driver is avoided because it
     * serializes closures into child processes, which fails with
     * dependency-injected tool classes.
     */
    protected function concurrencyDriver(): string
    {
        if (class_exists(Fork::class) && extension_loaded('pcntl')) {
            return 'fork';
        }

        return 'sync';
    }

    // ─── Single Tool Execution ───────────────────────────────────────────

    /**
     * Execute a single tool call with event dispatching and error handling.
     *
     * Tool exceptions are caught and their message is sent verbatim to the model
     * as an error ToolResult. Tool authors are responsible for ensuring exception
     * messages do not contain sensitive information (credentials, file paths, etc.).
     *
     * @param  array<string, mixed>  $meta
     */
    protected function executeSingleTool(ToolCall $toolCall, array $meta, ?int $stepNumber = null, ?string $agentKey = null, ?string $provider = null, ?string $model = null, ?string $traceId = null): ToolResult
    {
        $this->events->dispatch(new AgentToolCallStarted($toolCall, agentKey: $agentKey, stepNumber: $stepNumber, provider: $provider, model: $model, traceId: $traceId));

        try {
            $result = $this->dispatchTool($toolCall, $meta, $stepNumber, $agentKey);

            $this->events->dispatch(new AgentToolCallCompleted($toolCall, $result, agentKey: $agentKey, stepNumber: $stepNumber, provider: $provider, model: $model, traceId: $traceId));

            return $result;
        } catch (\Throwable $e) {
            $this->events->dispatch(new AgentToolCallFailed($toolCall, $e, agentKey: $agentKey, stepNumber: $stepNumber, provider: $provider, model: $model, traceId: $traceId));

            return new ToolResult(
                toolCall: $toolCall,
                content: $e->getMessage(),
                isError: true,
            );
        }
    }

    // ─── Usage ───────────────────────────────────────────────────────────

    /**
     * Merge usage across all steps into a single Usage instance.
     *
     * @param  array<int, Step>  $steps
     */
    protected function mergeUsage(array $steps): Usage
    {
        $merged = new Usage(0, 0);

        foreach ($steps as $step) {
            $merged = $merged->merge($step->usage);
        }

        return $merged;
    }
}
