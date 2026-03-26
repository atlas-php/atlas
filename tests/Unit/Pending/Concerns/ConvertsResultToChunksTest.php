<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Executor\ExecutorResult;
use Atlasphp\Atlas\Executor\Step;
use Atlasphp\Atlas\Executor\ToolResult;
use Atlasphp\Atlas\Messages\ToolCall;
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

function createChunkResult(string $text = 'Hello world', array $steps = [], ?string $reasoning = null): ExecutorResult
{
    return new ExecutorResult(
        text: $text,
        reasoning: $reasoning,
        steps: $steps !== [] ? $steps : [new Step(text: $text, toolCalls: [], toolResults: [], usage: new Usage(10, 20))],
        usage: new Usage(10, 20),
        finishReason: FinishReason::Stop,
        meta: [],
    );
}

it('yields text chunks and a done chunk', function () {
    config(['atlas.stream.chunk_delay_us' => 0]);
    AtlasConfig::refresh();

    $converter = createChunkConverter();
    $chunks = iterator_to_array($converter->convert(createChunkResult('Hello world')));

    $textChunks = array_filter($chunks, fn (StreamChunk $c) => $c->type === ChunkType::Text);
    $doneChunks = array_filter($chunks, fn (StreamChunk $c) => $c->type === ChunkType::Done);

    expect($textChunks)->not->toBeEmpty()
        ->and($doneChunks)->toHaveCount(1);
});

it('respects zero chunk delay config', function () {
    config(['atlas.stream.chunk_delay_us' => 0]);
    AtlasConfig::refresh();

    $converter = createChunkConverter();
    $start = microtime(true);
    iterator_to_array($converter->convert(createChunkResult('Word one word two word three word four word five')));
    $elapsed = (microtime(true) - $start) * 1000;

    // With 0 delay, should complete in under 50ms even with multiple chunks
    expect($elapsed)->toBeLessThan(50);
});

it('done chunk carries usage and finish reason', function () {
    config(['atlas.stream.chunk_delay_us' => 0]);
    AtlasConfig::refresh();

    $converter = createChunkConverter();
    $chunks = iterator_to_array($converter->convert(createChunkResult('Test')));

    $done = collect($chunks)->first(fn (StreamChunk $c) => $c->type === ChunkType::Done);

    expect($done->usage)->toBeInstanceOf(Usage::class)
        ->and($done->finishReason)->toBe(FinishReason::Stop);
});

// ─── Orchestration markers ──────────────────────────────────────────

it('yields step started and completed markers for each step', function () {
    config(['atlas.stream.chunk_delay_us' => 0]);
    AtlasConfig::refresh();

    $steps = [
        new Step(text: null, toolCalls: [], toolResults: [], usage: new Usage(5, 10)),
        new Step(text: 'final', toolCalls: [], toolResults: [], usage: new Usage(5, 10)),
    ];

    $converter = createChunkConverter();
    $chunks = iterator_to_array($converter->convert(createChunkResult('final', $steps)));

    $stepStarted = array_values(array_filter($chunks, fn (StreamChunk $c) => $c->type === ChunkType::StepStarted));
    $stepCompleted = array_values(array_filter($chunks, fn (StreamChunk $c) => $c->type === ChunkType::StepCompleted));

    expect($stepStarted)->toHaveCount(2)
        ->and($stepStarted[0]->stepNumber)->toBe(1)
        ->and($stepStarted[1]->stepNumber)->toBe(2)
        ->and($stepCompleted)->toHaveCount(2);
});

it('yields tool call started and completed markers', function () {
    config(['atlas.stream.chunk_delay_us' => 0]);
    AtlasConfig::refresh();

    $toolCall = new ToolCall('tc-1', 'web_search', ['q' => 'test']);
    $toolResult = new ToolResult(toolCall: $toolCall, content: 'Found 5 results');

    $steps = [
        new Step(text: null, toolCalls: [$toolCall], toolResults: [$toolResult], usage: new Usage(5, 10)),
        new Step(text: 'Answer', toolCalls: [], toolResults: [], usage: new Usage(5, 10)),
    ];

    $converter = createChunkConverter();
    $chunks = iterator_to_array($converter->convert(createChunkResult('Answer', $steps)));

    $tcStarted = array_values(array_filter($chunks, fn (StreamChunk $c) => $c->type === ChunkType::ToolCallStarted));
    $tcCompleted = array_values(array_filter($chunks, fn (StreamChunk $c) => $c->type === ChunkType::ToolCallCompleted));

    expect($tcStarted)->toHaveCount(1)
        ->and($tcStarted[0]->toolName)->toBe('web_search')
        ->and($tcStarted[0]->toolCallId)->toBe('tc-1')
        ->and($tcStarted[0]->stepNumber)->toBe(1)
        ->and($tcCompleted)->toHaveCount(1)
        ->and($tcCompleted[0]->toolContent)->toBe('Found 5 results')
        ->and($tcCompleted[0]->toolError)->toBeFalse();
});

it('yields tool call failed marker for error results', function () {
    config(['atlas.stream.chunk_delay_us' => 0]);
    AtlasConfig::refresh();

    $toolCall = new ToolCall('tc-1', 'fetch', ['url' => 'http://example.com']);
    $toolResult = new ToolResult(toolCall: $toolCall, content: 'Connection refused', isError: true);

    $steps = [
        new Step(text: null, toolCalls: [$toolCall], toolResults: [$toolResult], usage: new Usage(5, 10)),
        new Step(text: 'Sorry', toolCalls: [], toolResults: [], usage: new Usage(5, 10)),
    ];

    $converter = createChunkConverter();
    $chunks = iterator_to_array($converter->convert(createChunkResult('Sorry', $steps)));

    $tcFailed = array_values(array_filter($chunks, fn (StreamChunk $c) => $c->type === ChunkType::ToolCallFailed));

    expect($tcFailed)->toHaveCount(1)
        ->and($tcFailed[0]->toolName)->toBe('fetch')
        ->and($tcFailed[0]->toolError)->toBeTrue()
        ->and($tcFailed[0]->toolContent)->toBe('Connection refused');
});

it('yields orchestration markers in correct order', function () {
    config(['atlas.stream.chunk_delay_us' => 0]);
    AtlasConfig::refresh();

    $toolCall = new ToolCall('tc-1', 'search', []);
    $toolResult = new ToolResult(toolCall: $toolCall, content: 'ok');

    $steps = [
        new Step(text: null, toolCalls: [$toolCall], toolResults: [$toolResult], usage: new Usage(5, 10)),
        new Step(text: 'Done', toolCalls: [], toolResults: [], usage: new Usage(5, 10)),
    ];

    $converter = createChunkConverter();
    $chunks = iterator_to_array($converter->convert(createChunkResult('Done', $steps)));

    $types = array_map(fn (StreamChunk $c) => $c->type, $chunks);

    // Expected order: StepStarted, ToolCallStarted, ToolCallCompleted, ToolCall, StepCompleted,
    //                 StepStarted, StepCompleted, Text..., Done
    $firstFiveTypes = array_slice($types, 0, 5);
    expect($firstFiveTypes)->toBe([
        ChunkType::StepStarted,
        ChunkType::ToolCallStarted,
        ChunkType::ToolCallCompleted,
        ChunkType::ToolCall,
        ChunkType::StepCompleted,
    ]);

    // Next step
    expect($types[5])->toBe(ChunkType::StepStarted)
        ->and($types[6])->toBe(ChunkType::StepCompleted);

    // Text and Done at the end
    expect(end($types))->toBe(ChunkType::Done);
});

// ─── Reasoning ──────────────────────────────────────────────────────

it('yields thinking chunk when reasoning is present', function () {
    config(['atlas.stream.chunk_delay_us' => 0]);
    AtlasConfig::refresh();

    $converter = createChunkConverter();
    $chunks = iterator_to_array($converter->convert(createChunkResult('Answer', reasoning: 'Let me think...')));

    $thinkingChunks = array_filter($chunks, fn (StreamChunk $c) => $c->type === ChunkType::Thinking);

    expect($thinkingChunks)->toHaveCount(1)
        ->and(array_values($thinkingChunks)[0]->reasoning)->toBe('Let me think...');
});

it('does not yield thinking chunk when reasoning is null', function () {
    config(['atlas.stream.chunk_delay_us' => 0]);
    AtlasConfig::refresh();

    $converter = createChunkConverter();
    $chunks = iterator_to_array($converter->convert(createChunkResult('Answer')));

    $thinkingChunks = array_filter($chunks, fn (StreamChunk $c) => $c->type === ChunkType::Thinking);

    expect($thinkingChunks)->toBeEmpty();
});

it('does not yield thinking chunk when reasoning is empty string', function () {
    config(['atlas.stream.chunk_delay_us' => 0]);
    AtlasConfig::refresh();

    $converter = createChunkConverter();
    $chunks = iterator_to_array($converter->convert(createChunkResult('Answer', reasoning: '')));

    $thinkingChunks = array_filter($chunks, fn (StreamChunk $c) => $c->type === ChunkType::Thinking);

    expect($thinkingChunks)->toBeEmpty();
});
