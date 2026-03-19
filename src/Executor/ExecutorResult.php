<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Executor;

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Responses\Usage;

/**
 * The final output of an executor run.
 *
 * Contains the complete execution trace including all steps,
 * merged token usage, and the model's final text response.
 */
class ExecutorResult
{
    /**
     * @param  array<int, Step>  $steps
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $text,
        public readonly ?string $reasoning,
        public readonly array $steps,
        public readonly Usage $usage,
        public readonly FinishReason $finishReason,
        public readonly array $meta,
    ) {}

    /**
     * Get the total number of round trips executed.
     */
    public function totalSteps(): int
    {
        return count($this->steps);
    }

    /**
     * Get the total number of tool calls across all steps.
     */
    public function totalToolCalls(): int
    {
        $count = 0;

        foreach ($this->steps as $step) {
            $count += count($step->toolCalls);
        }

        return $count;
    }

    /**
     * Get a flat array of all tool calls across all steps.
     *
     * @return array<int, ToolCall>
     */
    public function allToolCalls(): array
    {
        $toolCalls = [];

        foreach ($this->steps as $step) {
            foreach ($step->toolCalls as $toolCall) {
                $toolCalls[] = $toolCall;
            }
        }

        return $toolCalls;
    }
}
