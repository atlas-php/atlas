<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending\Concerns;

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Executor\ExecutorResult;
use Atlasphp\Atlas\Responses\StreamChunk;

/**
 * Converts an ExecutorResult into a generator of StreamChunks for streaming.
 *
 * Used by both TextRequest and AgentRequest to convert tool-loop
 * results into a streamable format with orchestration markers, tool calls,
 * text segments, and a Done chunk carrying usage and finish reason.
 */
trait ConvertsResultToChunks
{
    /**
     * Convert an ExecutorResult into a generator of StreamChunks.
     *
     * Yields interleaved orchestration markers (step/tool lifecycle), then
     * reasoning if present, text segments split on word boundaries, and
     * a Done chunk with full usage and finish reason.
     */
    protected function resultToChunks(ExecutorResult $result): \Generator
    {
        $stepNumber = 0;

        foreach ($result->steps as $step) {
            $stepNumber++;

            yield new StreamChunk(
                type: ChunkType::StepStarted,
                stepNumber: $stepNumber,
            );

            // Yield tool call lifecycle markers
            foreach ($step->toolCalls as $index => $toolCall) {
                yield new StreamChunk(
                    type: ChunkType::ToolCallStarted,
                    stepNumber: $stepNumber,
                    toolName: $toolCall->name,
                    toolCallId: $toolCall->id,
                );

                // Match tool result by index — alignment is guaranteed by AgentExecutor
                // which builds the toolResults array in the same order as toolCalls,
                // both for sequential and concurrent execution paths.
                $toolResult = $step->toolResults[$index] ?? null;

                if ($toolResult !== null && $toolResult->isError) {
                    yield new StreamChunk(
                        type: ChunkType::ToolCallFailed,
                        stepNumber: $stepNumber,
                        toolName: $toolCall->name,
                        toolCallId: $toolCall->id,
                        toolContent: mb_substr($toolResult->content, 0, 500),
                        toolError: true,
                    );
                } elseif ($toolResult !== null) {
                    yield new StreamChunk(
                        type: ChunkType::ToolCallCompleted,
                        stepNumber: $stepNumber,
                        toolName: $toolCall->name,
                        toolCallId: $toolCall->id,
                        toolContent: mb_substr($toolResult->content, 0, 500),
                        toolError: false,
                    );
                }
            }

            // Yield the existing ToolCall chunk (tool definitions for the stream consumer)
            if ($step->toolCalls !== []) {
                yield new StreamChunk(
                    type: ChunkType::ToolCall,
                    toolCalls: $step->toolCalls,
                );
            }

            yield new StreamChunk(
                type: ChunkType::StepCompleted,
                stepNumber: $stepNumber,
            );
        }

        // Yield reasoning as a Thinking chunk (matches direct stream path behavior)
        if ($result->reasoning !== null && $result->reasoning !== '') {
            yield new StreamChunk(type: ChunkType::Thinking, reasoning: $result->reasoning);
        }

        // Yield text chunks split on word boundaries
        if ($result->text !== '') {
            $segments = preg_split('/(?<=\s)/', $result->text, -1, PREG_SPLIT_NO_EMPTY) ?: [$result->text];

            foreach ($segments as $segment) {
                yield new StreamChunk(type: ChunkType::Text, text: $segment);

                // Small delay between chunks so broadcast consumers (WebSocket UI)
                // receive them as a visible typing stream rather than one instant batch.
                // Set ATLAS_STREAM_CHUNK_DELAY_US=0 in tests or CLI to eliminate the delay.
                $delay = (int) config('atlas.stream.chunk_delay_us', 15_000);

                if ($delay > 0) {
                    usleep($delay);
                }
            }
        }

        // Done chunk with full execution context
        yield new StreamChunk(
            type: ChunkType::Done,
            usage: $result->usage,
            finishReason: $result->finishReason,
        );
    }
}
