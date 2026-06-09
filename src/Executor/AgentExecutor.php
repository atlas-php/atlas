<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Executor;

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Enums\ToolChoiceMode;
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
use Atlasphp\Atlas\Middleware\MiddlewareResolver;
use Atlasphp\Atlas\Middleware\MiddlewareStack;
use Atlasphp\Atlas\Middleware\StepContext;
use Atlasphp\Atlas\Middleware\ToolContext;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;
use Atlasphp\Atlas\Tools\Tool;
use Atlasphp\Atlas\Tools\ToolChoice;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
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
    protected ExecutionContext $context;

    public function __construct(
        protected readonly Driver $driver,
        protected readonly ToolExecutor $toolExecutor,
        protected readonly Dispatcher $events,
        protected readonly ?MiddlewareStack $middlewareStack = null,
        protected readonly ?MiddlewareResolver $middlewareResolver = null,
    ) {
        $this->context = new ExecutionContext;
    }

    /**
     * Create an executor from a set of Tool instances.
     *
     * ToolRegistry and ToolExecutor are stateless value objects with no external
     * dependencies — direct instantiation is intentional to avoid container overhead.
     *
     * @param  array<int, Tool>  $tools
     */
    public static function forTools(
        Driver $driver,
        array $tools,
        Dispatcher $events,
        ?MiddlewareStack $middlewareStack = null,
        ?MiddlewareResolver $middlewareResolver = null,
    ): self {
        return new self(
            driver: $driver,
            toolExecutor: new ToolExecutor(new ToolRegistry($tools)),
            events: $events,
            middlewareStack: $middlewareStack,
            middlewareResolver: $middlewareResolver,
        );
    }

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
        ?ExecutionContext $context = null,
    ): ExecutorResult {
        $this->context = $context ?? new ExecutionContext;

        $steps = [];
        $stepCount = 0;
        $totalUsage = null;
        $accumulatedUsage = new Usage(0, 0);
        $allProviderToolCalls = [];
        $allAnnotations = [];
        $lastFinishReason = null;

        $this->events->dispatch(new AgentStarted(
            agentKey: $this->context->agentKey,
            maxSteps: $maxSteps,
            concurrent: $concurrent,
            provider: $this->context->provider,
            model: $this->context->model,
            traceId: $this->context->traceId,
            channel: $this->context->broadcastChannel,
        ));

        try {
            while (true) {
                $stepCount++;

                if ($maxSteps !== null && $stepCount > $maxSteps) {
                    $this->events->dispatch(new AgentMaxStepsExceeded($maxSteps, $steps, agentKey: $this->context->agentKey, provider: $this->context->provider, model: $this->context->model, traceId: $this->context->traceId, channel: $this->context->broadcastChannel));

                    throw new MaxStepsExceededException($maxSteps, $stepCount);
                }

                $this->events->dispatch(new AgentStepStarted($stepCount, agentKey: $this->context->agentKey, provider: $this->context->provider, model: $this->context->model, traceId: $this->context->traceId, channel: $this->context->broadcastChannel));

                $response = $this->dispatchStep($request, $stepCount, $steps, $accumulatedUsage, $meta);

                $lastFinishReason = $response->finishReason;

                $this->events->dispatch(new AgentStepCompleted(
                    stepNumber: $stepCount,
                    finishReason: $response->finishReason,
                    usage: $response->usage,
                    agentKey: $this->context->agentKey,
                    provider: $this->context->provider,
                    model: $this->context->model,
                    traceId: $this->context->traceId,
                    channel: $this->context->broadcastChannel,
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

                // A ToolCalls finish with no CLIENT tool calls means the provider
                // is doing server-side work (e.g. Anthropic web search returning
                // `pause_turn`). There's nothing for us to execute, and re-POSTing
                // an empty assistant turn would loop until maxSteps. Surface what
                // we have and stop cleanly.
                if ($response->toolCalls === []) {
                    $steps[] = new Step(
                        text: $response->text,
                        toolCalls: [],
                        toolResults: [],
                        usage: $response->usage,
                    );

                    break;
                }

                $toolResults = $concurrent
                    ? $this->executeToolsConcurrently($response->toolCalls, $meta, $stepCount)
                    : $this->executeToolsSequentially($response->toolCalls, $meta, $stepCount);

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

                // A forced tool choice applies only to the opening step. Relaxing
                // it to `auto` after step 1 lets the model produce a final text
                // reply on a later step — a static `required` would compel a tool
                // call every step and loop until maxSteps.
                if ($stepCount === 1 && $request->toolChoice?->mode === ToolChoiceMode::Required) {
                    $request = $request->withToolChoice(ToolChoice::auto());
                }
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
                agentKey: $this->context->agentKey,
                finishReason: $lastFinishReason,
                provider: $this->context->provider,
                model: $this->context->model,
                traceId: $this->context->traceId,
                channel: $this->context->broadcastChannel,
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
    ): TextResponse {
        if ($this->middlewareStack === null) {
            return $this->driver->text($request);
        }

        $middleware = $this->middlewareResolver?->forLayer('step') ?? [];

        if ($middleware === []) {
            return $this->driver->text($request);
        }

        $context = new StepContext(
            stepNumber: $stepNumber,
            request: $request,
            accumulatedUsage: $accumulatedUsage,
            previousSteps: $previousSteps,
            meta: $meta,
            agentKey: $this->context->agentKey,
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
    protected function dispatchTool(ToolCall $toolCall, array $meta, ?int $stepNumber = null): ToolResult
    {
        if ($this->middlewareStack === null) {
            return $this->toolExecutor->execute($toolCall, $meta);
        }

        $middleware = $this->middlewareResolver?->forLayer('tool') ?? [];

        if ($middleware === []) {
            return $this->toolExecutor->execute($toolCall, $meta);
        }

        $context = new ToolContext(
            toolCall: $toolCall,
            meta: $meta,
            stepNumber: $stepNumber,
            agentKey: $this->context->agentKey,
            isDelegation: $this->toolExecutor->isDelegation($toolCall->name),
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
    protected function executeToolsSequentially(array $toolCalls, array $meta, ?int $stepNumber = null): array
    {
        $results = [];

        foreach ($toolCalls as $toolCall) {
            $results[] = $this->executeSingleTool($toolCall, $meta, $stepNumber);
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
     * Sub-agent delegations run concurrently too. Each fork inherits a copy of
     * the parent's (scoped) ExecutionService, so every child computes its own
     * parent_execution_id / parent_tool_call_id and persists correct lineage
     * independently — nothing has to propagate back, and usage roll-up is read
     * from the DB subtree afterwards. The one hazard is the inherited database
     * connection, which is reset before forking (see disconnectDatabase).
     *
     * Event delivery caveat: the per-tool AgentToolCallStarted/Completed events
     * are fired here in the parent and behave normally. But any orchestration
     * events a delegated sub-agent fires *inside* its fork (its own AgentStarted/
     * AgentCompleted, step and nested tool-call events) are dispatched to the
     * child's in-process dispatcher and do NOT reach listeners in the parent
     * process — there is no IPC back. Persistence is unaffected (the child writes
     * the full execution/step/tool-call tree to the DB), so post-hoc auditing is
     * complete; only real-time in-process listeners on a sub-agent's internals
     * are missed. Use sequential delegation when that live observability matters.
     *
     * Falls back to sequential if only one tool call is present.
     *
     * @param  array<int, ToolCall>  $toolCalls
     * @param  array<string, mixed>  $meta
     * @return array<int, ToolResult>
     */
    protected function executeToolsConcurrently(array $toolCalls, array $meta, ?int $stepNumber = null): array
    {
        if (count($toolCalls) === 1) {
            return $this->executeToolsSequentially($toolCalls, $meta, $stepNumber);
        }

        $toolCalls = array_values($toolCalls);

        // Fire AgentToolCallStarted events upfront for all tools
        foreach ($toolCalls as $toolCall) {
            $this->events->dispatch(new AgentToolCallStarted($toolCall, agentKey: $this->context->agentKey, stepNumber: $stepNumber, provider: $this->context->provider, model: $this->context->model, traceId: $this->context->traceId, channel: $this->context->broadcastChannel));
        }

        // Build closures that always return ToolResult — never Throwable.
        // This ensures return values serialize cleanly across fork boundaries.
        $tasks = array_map(
            fn (ToolCall $toolCall) => function () use ($toolCall, $meta, $stepNumber): ToolResult {
                try {
                    return $this->dispatchTool($toolCall, $meta, $stepNumber);
                } catch (\Throwable $e) {
                    return new ToolResult(
                        toolCall: $toolCall,
                        content: $e->getMessage(),
                        isError: true,
                        exceptionClass: $e::class,
                    );
                }
            },
            $toolCalls,
        );

        $driver = $this->concurrencyDriver();

        // Fork DB-safety: a forked child inherits the parent's database connection
        // — a single, non-fork-safe socket. Delegating tools write heavily to the
        // DB (nested executions, steps, tool calls), so concurrent children sharing
        // one connection would corrupt the wire protocol. Closing the parent's
        // resolved connections before forking forces each child to open its own;
        // the parent reconnects lazily on its next query after the batch. Guarded
        // to the fork driver + persistence so the in-process sync driver and
        // non-persistent runs are untouched (and an in-memory SQLite test DB,
        // which only exists for the life of its connection, is never dropped).
        if ($driver === 'fork' && (bool) config('atlas.persistence.enabled', false)) {
            $this->disconnectDatabase();
        }

        /** @var array<int, ToolResult> $results */
        $results = $this->runConcurrentTasks($driver, $tasks);

        // Post-process: fire completion or error events
        foreach ($results as $result) {
            if ($result->isError) {
                $exception = $this->reconstructException($result->exceptionClass, $result->content);
                $this->events->dispatch(new AgentToolCallFailed($result->toolCall, $exception, agentKey: $this->context->agentKey, stepNumber: $stepNumber, provider: $this->context->provider, model: $this->context->model, traceId: $this->context->traceId, channel: $this->context->broadcastChannel));
            } else {
                $this->events->dispatch(new AgentToolCallCompleted($result->toolCall, $result, agentKey: $this->context->agentKey, stepNumber: $stepNumber, provider: $this->context->provider, model: $this->context->model, traceId: $this->context->traceId, channel: $this->context->broadcastChannel));
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

    /**
     * Purge every resolved database connection so forked children each open
     * their own. Laravel rebuilds connections lazily, so the parent and every
     * child get a clean, fork-safe connection. Called in the parent right before
     * forking — no live socket is shared across the fork boundary. No-op when no
     * connection has been resolved yet.
     */
    protected function disconnectDatabase(): void
    {
        foreach (array_keys(DB::getConnections()) as $name) {
            DB::purge($name);
        }
    }

    /**
     * Dispatch the prepared tool tasks on the resolved concurrency driver.
     *
     * Isolated from executeToolsConcurrently so the fork DB-safety guard and the
     * driver dispatch can be exercised independently in tests without forking.
     *
     * @param  array<int, callable(): ToolResult>  $tasks
     * @return array<int, ToolResult>
     */
    protected function runConcurrentTasks(string $driver, array $tasks): array
    {
        return Concurrency::driver($driver)->run($tasks);
    }

    /**
     * Reconstruct an exception from its class name and message.
     *
     * Used to preserve exception types across fork boundaries in concurrent
     * execution, where full Throwable objects cannot be serialized.
     */
    protected function reconstructException(?string $class, string $message): \Throwable
    {
        if ($class !== null && class_exists($class) && is_a($class, \Throwable::class, true)) {
            return new $class($message);
        }

        return new \RuntimeException($message);
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
    protected function executeSingleTool(ToolCall $toolCall, array $meta, ?int $stepNumber = null): ToolResult
    {
        $this->events->dispatch(new AgentToolCallStarted($toolCall, agentKey: $this->context->agentKey, stepNumber: $stepNumber, provider: $this->context->provider, model: $this->context->model, traceId: $this->context->traceId, channel: $this->context->broadcastChannel));

        try {
            $result = $this->dispatchTool($toolCall, $meta, $stepNumber);

            $this->events->dispatch(new AgentToolCallCompleted($toolCall, $result, agentKey: $this->context->agentKey, stepNumber: $stepNumber, provider: $this->context->provider, model: $this->context->model, traceId: $this->context->traceId, channel: $this->context->broadcastChannel));

            return $result;
        } catch (\Throwable $e) {
            $this->events->dispatch(new AgentToolCallFailed($toolCall, $e, agentKey: $this->context->agentKey, stepNumber: $stepNumber, provider: $this->context->provider, model: $this->context->model, traceId: $this->context->traceId, channel: $this->context->broadcastChannel));

            return new ToolResult(
                toolCall: $toolCall,
                content: $e->getMessage(),
                isError: true,
                exceptionClass: $e::class,
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
