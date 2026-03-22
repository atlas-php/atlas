<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Executor;

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;

/**
 * The final output of an executor run.
 *
 * Contains the complete execution trace including all steps,
 * merged token usage, and the model's final text response.
 */
class ExecutorResult
{
    /** @var int|null Set by PersistConversation middleware. */
    public ?int $conversationId = null;

    /** @var int|null Set by TrackExecution middleware. */
    public ?int $executionId = null;

    /**
     * @param  array<int, Step>  $steps
     * @param  array<string, mixed>  $meta
     * @param  array<int, array<string, mixed>>  $providerToolCalls
     * @param  array<int, array<string, mixed>>  $annotations
     */
    public function __construct(
        public readonly string $text,
        public readonly ?string $reasoning,
        public readonly array $steps,
        public readonly Usage $usage,
        public readonly FinishReason $finishReason,
        public readonly array $meta,
        public readonly array $providerToolCalls = [],
        public readonly array $annotations = [],
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
     * Convert this executor result to a TextResponse.
     *
     * @param  array<string, mixed>  $extraMeta  Additional meta to merge (e.g. conversation_id, execution_id)
     */
    public function toTextResponse(array $extraMeta = []): TextResponse
    {
        return new TextResponse(
            text: $this->text,
            usage: $this->usage,
            finishReason: $this->finishReason,
            toolCalls: $this->allToolCalls(),
            reasoning: $this->reasoning,
            steps: $this->steps,
            meta: $extraMeta !== [] ? array_merge($this->meta, $extraMeta) : $this->meta,
            providerToolCalls: $this->providerToolCalls,
            annotations: $this->annotations,
        );
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
