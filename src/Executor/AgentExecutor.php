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

            $toolResults = [];

            foreach ($response->toolCalls as $toolCall) {
                $toolResults[] = $this->executeSingleTool($toolCall, $meta);
            }

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
