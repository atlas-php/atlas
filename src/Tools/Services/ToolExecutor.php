<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools\Services;

use Atlasphp\Atlas\Pipelines\PipelineRunner;
use Atlasphp\Atlas\Tools\Contracts\ToolContract;
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
     * Execute a tool with the given arguments.
     *
     * @param  ToolContract  $tool  The tool to execute.
     * @param  array<string, mixed>  $args  The arguments to pass.
     * @param  ToolContext  $context  The execution context.
     */
    public function execute(ToolContract $tool, array $args, ToolContext $context): ToolResult
    {
        try {
            // Run before_execute pipeline
            $pipelineData = [
                'tool' => $tool,
                'args' => $args,
                'context' => $context,
            ];

            /** @var array{tool: ToolContract, args: array<string, mixed>, context: ToolContext} $pipelineData */
            $pipelineData = $this->pipelineRunner->runIfActive(
                'tool.before_execute',
                $pipelineData,
            );

            // Execute the tool
            $result = $tool->handle($pipelineData['args'], $pipelineData['context']);

            // Run after_execute pipeline
            $afterData = [
                'tool' => $tool,
                'args' => $pipelineData['args'],
                'context' => $pipelineData['context'],
                'result' => $result,
            ];

            /** @var array{result: ToolResult} $afterData */
            $afterData = $this->pipelineRunner->runIfActive(
                'tool.after_execute',
                $afterData,
            );

            return $afterData['result'];
        } catch (ToolException $e) {
            $this->handleToolError($tool, $args, $context, $e);

            return ToolResult::error($e->getMessage());
        } catch (Throwable $e) {
            $this->handleToolError($tool, $args, $context, $e);

            return ToolResult::error(
                "Tool '{$tool->name()}' failed: {$e->getMessage()}",
            );
        }
    }

    /**
     * Handle a tool execution error by running the error pipeline.
     *
     * @param  ToolContract  $tool  The tool that failed.
     * @param  array<string, mixed>  $args  The arguments that were passed.
     * @param  ToolContext  $context  The execution context.
     * @param  Throwable  $exception  The exception that occurred.
     */
    protected function handleToolError(
        ToolContract $tool,
        array $args,
        ToolContext $context,
        Throwable $exception,
    ): void {
        $errorData = [
            'tool' => $tool,
            'args' => $args,
            'context' => $context,
            'exception' => $exception,
        ];

        $this->pipelineRunner->runIfActive('tool.on_error', $errorData);
    }
}
