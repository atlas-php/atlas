<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools\Services;

use Atlasphp\Atlas\Pipelines\PipelineRunner;
use Atlasphp\Atlas\Tools\Contracts\ToolContract;
use Atlasphp\Atlas\Tools\Events\ToolExecuted;
use Atlasphp\Atlas\Tools\Events\ToolExecuting;
use Atlasphp\Atlas\Tools\Exceptions\ToolException;
use Atlasphp\Atlas\Tools\Support\ToolContext;
use Atlasphp\Atlas\Tools\Support\ToolResult;
use Throwable;

/**
 * Executes tools with pipeline middleware support.
 *
 * Handles tool invocation with before/after pipeline hooks.
 * Catches exceptions and converts them to error results.
 */
class ToolExecutor
{
    public function __construct(
        protected PipelineRunner $pipelineRunner,
    ) {}

    /**
     * Execute a tool with the given parameters.
     *
     * @param  ToolContract  $tool  The tool to execute.
     * @param  array<string, mixed>  $params  The parameter values to pass.
     * @param  ToolContext  $context  The execution context.
     */
    public function execute(ToolContract $tool, array $params, ToolContext $context): ToolResult
    {
        try {
            // Run before_execute pipeline
            $pipelineData = [
                'tool' => $tool,
                'params' => $params,
                'context' => $context,
            ];

            /** @var array{tool: ToolContract, params: array<string, mixed>, context: ToolContext} $pipelineData */
            $pipelineData = $this->pipelineRunner->runIfActive(
                'tool.before_execute',
                $pipelineData,
            );

            // Dispatch executing event (after pipeline)
            $this->dispatchEvent(new ToolExecuting(
                $pipelineData['tool'],
                $pipelineData['params'],
                $pipelineData['context'],
            ));

            // Execute the tool with timing
            $startTime = microtime(true);
            $result = $tool->handle($pipelineData['params'], $pipelineData['context']);
            $duration = (microtime(true) - $startTime) * 1000;

            // Run after_execute pipeline
            $afterData = [
                'tool' => $tool,
                'params' => $pipelineData['params'],
                'context' => $pipelineData['context'],
                'result' => $result,
            ];

            /** @var array{result: ToolResult} $afterData */
            $afterData = $this->pipelineRunner->runIfActive(
                'tool.after_execute',
                $afterData,
            );

            // Dispatch executed event (after pipeline, with duration)
            $this->dispatchEvent(new ToolExecuted(
                $pipelineData['tool'],
                $pipelineData['params'],
                $pipelineData['context'],
                $afterData['result'],
                $duration,
            ));

            return $afterData['result'];
        } catch (ToolException $e) {
            $this->handleToolError($tool, $params, $context, $e);

            return ToolResult::error($e->getMessage());
        } catch (Throwable $e) {
            $this->handleToolError($tool, $params, $context, $e);

            return ToolResult::error(
                "Tool '{$tool->name()}' failed: {$e->getMessage()}",
            );
        }
    }

    /**
     * Dispatch an event if events are enabled.
     */
    protected function dispatchEvent(object $event): void
    {
        if (config('atlas.events.enabled', true) === false) {
            return;
        }

        event($event);
    }

    /**
     * Handle a tool execution error by running the error pipeline.
     *
     * @param  ToolContract  $tool  The tool that failed.
     * @param  array<string, mixed>  $params  The parameter values that were passed.
     * @param  ToolContext  $context  The execution context.
     * @param  Throwable  $exception  The exception that occurred.
     */
    protected function handleToolError(
        ToolContract $tool,
        array $params,
        ToolContext $context,
        Throwable $exception,
    ): void {
        $errorData = [
            'tool' => $tool,
            'params' => $params,
            'context' => $context,
            'exception' => $exception,
        ];

        $this->pipelineRunner->runIfActive('tool.on_error', $errorData);
    }
}
