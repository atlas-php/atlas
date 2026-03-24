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
 * results into a streamable format with tool calls, text segments,
 * and a Done chunk carrying usage and finish reason.
 */
trait ConvertsResultToChunks
{
    /**
     * Convert an ExecutorResult into a generator of StreamChunks.
     *
     * Yields tool call chunks for each step, then text segments split on
     * word boundaries, and a Done chunk with full usage and finish reason.
     */
    protected function resultToChunks(ExecutorResult $result): \Generator
    {
        // Yield tool call chunks so consumers see what tools were called
        foreach ($result->steps as $step) {
            if ($step->toolCalls !== []) {
                yield new StreamChunk(
                    type: ChunkType::ToolCall,
                    toolCalls: $step->toolCalls,
                );
            }
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
            reasoning: $result->reasoning,
        );
    }
}
