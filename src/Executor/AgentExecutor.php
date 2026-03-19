<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Executor;

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Events\AgentCompleted;
use Atlasphp\Atlas\Events\AgentToolCalled;
use Atlasphp\Atlas\Events\AgentToolCalling;
use Atlasphp\Atlas\Events\AgentToolErrored;
use Atlasphp\Atlas\Exceptions\MaxStepsExceededException;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\Usage;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Concurrency;

/**
 * Orchestrates the tool call loop for agent execution.
 *
 * Calls the driver, executes any requested tools, appends results
 * to the conversation, and repeats until the model stops or the
 * step limit is reached.
 */
class AgentExecutor
{
    public function __construct(
        protected readonly Driver $driver,
        protected readonly ToolExecutor $toolExecutor,
        protected readonly Dispatcher $events,
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
        bool $parallelToolCalls = true,
        array $meta = [],
    ): ExecutorResult {
        $steps = [];
        $stepCount = 0;

        while (true) {
            $stepCount++;

            if ($maxSteps !== null && $stepCount > $maxSteps) {
                throw new MaxStepsExceededException($maxSteps, $stepCount);
            }

            $response = $this->driver->text($request);

            if ($response->finishReason !== FinishReason::ToolCalls) {
                $steps[] = new Step(
                    text: $response->text,
                    toolCalls: $response->toolCalls,
                    toolResults: [],
                    usage: $response->usage,
                );

                break;
            }

            $toolResults = $parallelToolCalls
                ? $this->executeToolsConcurrently($response->toolCalls, $meta)
                : $this->executeToolsSequentially($response->toolCalls, $meta);

            $steps[] = new Step(
                text: $response->text,
                toolCalls: $response->toolCalls,
                toolResults: $toolResults,
                usage: $response->usage,
            );

            $messagesToAppend = [$response->toMessage()];

            foreach ($toolResults as $toolResult) {
                $messagesToAppend[] = $toolResult->toMessage();
            }

            $request = $request->withAppendedMessages($messagesToAppend);
        }

        $this->events->dispatch(new AgentCompleted($steps));

        return new ExecutorResult(
            text: $response->text,
            reasoning: $response->reasoning,
            steps: $steps,
            usage: $this->mergeUsage($steps),
            finishReason: $response->finishReason,
            meta: $response->meta,
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
    protected function executeToolsSequentially(array $toolCalls, array $meta): array
    {
        $results = [];

        foreach ($toolCalls as $toolCall) {
            $results[] = $this->executeSingleTool($toolCall, $meta);
        }

        return $results;
    }

    // ─── Concurrent Execution ────────────────────────────────────────────

    /**
     * Execute tool calls concurrently using Laravel's Concurrency facade.
     *
     * Falls back to sequential if only one tool call is present.
     *
     * @param  array<int, ToolCall>  $toolCalls
     * @param  array<string, mixed>  $meta
     * @return array<int, ToolResult>
     */
    protected function executeToolsConcurrently(array $toolCalls, array $meta): array
    {
        if (count($toolCalls) === 1) {
            return $this->executeToolsSequentially($toolCalls, $meta);
        }

        $toolCalls = array_values($toolCalls);

        // Fire AgentToolCalling events upfront for all tools
        foreach ($toolCalls as $toolCall) {
            $this->events->dispatch(new AgentToolCalling($toolCall));
        }

        // Build closures — each catches its own errors so Concurrency::run() never throws
        $tasks = array_map(
            fn (ToolCall $toolCall) => function () use ($toolCall, $meta): ToolResult|\Throwable {
                try {
                    return $this->toolExecutor->execute($toolCall, $meta);
                } catch (\Throwable $e) {
                    return $e;
                }
            },
            $toolCalls,
        );

        // Run concurrently — each returns a ToolResult or a caught Throwable
        $rawResults = Concurrency::run($tasks);

        // Post-process: fire events, convert errors to ToolResults
        $results = [];

        foreach ($toolCalls as $i => $toolCall) {
            $result = $rawResults[$i];

            if ($result instanceof \Throwable) {
                $this->events->dispatch(new AgentToolErrored($toolCall, $result));

                $results[] = new ToolResult(
                    toolCall: $toolCall,
                    content: $result->getMessage(),
                    isError: true,
                );
            } elseif ($result instanceof ToolResult) {
                $this->events->dispatch(new AgentToolCalled($toolCall, $result));
                $results[] = $result;
            }
        }

        return $results;
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
    protected function executeSingleTool(ToolCall $toolCall, array $meta): ToolResult
    {
        $this->events->dispatch(new AgentToolCalling($toolCall));

        try {
            $result = $this->toolExecutor->execute($toolCall, $meta);

            $this->events->dispatch(new AgentToolCalled($toolCall, $result));

            return $result;
        } catch (\Throwable $e) {
            $this->events->dispatch(new AgentToolErrored($toolCall, $e));

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
