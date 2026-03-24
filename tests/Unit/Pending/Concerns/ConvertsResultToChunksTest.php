<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Executor\ExecutorResult;
use Atlasphp\Atlas\Executor\Step;
use Atlasphp\Atlas\Pending\Concerns\ConvertsResultToChunks;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\Usage;

function createChunkConverter(): object
{
    return new class
    {
        use ConvertsResultToChunks;

        /** Expose for testing. */
        public function convert(ExecutorResult $result): Generator
        {
            return $this->resultToChunks($result);
        }
    };
}

function createChunkResult(string $text = 'Hello world', array $steps = []): ExecutorResult
{
    return new ExecutorResult(
        text: $text,
        reasoning: null,
        steps: $steps !== [] ? $steps : [new Step(text: $text, toolCalls: [], toolResults: [], usage: new Usage(10, 20))],
        usage: new Usage(10, 20),
        finishReason: FinishReason::Stop,
        meta: [],
    );
}

it('yields text chunks and a done chunk', function () {
    config(['atlas.stream.chunk_delay_us' => 0]);

    $converter = createChunkConverter();
    $chunks = iterator_to_array($converter->convert(createChunkResult('Hello world')));

    $textChunks = array_filter($chunks, fn (StreamChunk $c) => $c->type === ChunkType::Text);
    $doneChunks = array_filter($chunks, fn (StreamChunk $c) => $c->type === ChunkType::Done);

    expect($textChunks)->not->toBeEmpty()
        ->and($doneChunks)->toHaveCount(1);
});

it('respects zero chunk delay config', function () {
    config(['atlas.stream.chunk_delay_us' => 0]);

    $converter = createChunkConverter();
    $start = microtime(true);
    iterator_to_array($converter->convert(createChunkResult('Word one word two word three word four word five')));
    $elapsed = (microtime(true) - $start) * 1000;

    // With 0 delay, should complete in under 50ms even with multiple chunks
    expect($elapsed)->toBeLessThan(50);
});

it('done chunk carries usage and finish reason', function () {
    config(['atlas.stream.chunk_delay_us' => 0]);

    $converter = createChunkConverter();
    $chunks = iterator_to_array($converter->convert(createChunkResult('Test')));

    $done = collect($chunks)->first(fn (StreamChunk $c) => $c->type === ChunkType::Done);

    expect($done->usage)->toBeInstanceOf(Usage::class)
        ->and($done->finishReason)->toBe(FinishReason::Stop);
});
