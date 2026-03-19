<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\Usage;

it('accumulates text from chunks during iteration', function () {
    $chunks = [
        new StreamChunk(ChunkType::Text, text: 'Hello'),
        new StreamChunk(ChunkType::Text, text: ' world'),
        new StreamChunk(ChunkType::Done),
    ];

    $stream = new StreamResponse($chunks);

    foreach ($stream as $chunk) {
        // consume
    }

    expect($stream->getText())->toBe('Hello world');
});

it('does not accumulate text from non-text chunks', function () {
    $chunks = [
        new StreamChunk(ChunkType::Thinking, reasoning: 'Let me think...'),
        new StreamChunk(ChunkType::Text, text: 'Answer'),
        new StreamChunk(ChunkType::ToolCall),
    ];

    $stream = new StreamResponse($chunks);

    foreach ($stream as $chunk) {
        // consume
    }

    expect($stream->getText())->toBe('Answer');
});

it('populates usage from Done chunk after iteration', function () {
    $stream = new StreamResponse([
        new StreamChunk(ChunkType::Text, text: 'Hi'),
        new StreamChunk(ChunkType::Done, usage: new Usage(100, 50), finishReason: FinishReason::Stop),
    ]);

    foreach ($stream as $chunk) {
        // consume
    }

    expect($stream->getUsage())->not->toBeNull();
    expect($stream->getUsage()->inputTokens)->toBe(100);
    expect($stream->getUsage()->outputTokens)->toBe(50);
});

it('populates finishReason from Done chunk after iteration', function () {
    $stream = new StreamResponse([
        new StreamChunk(ChunkType::Text, text: 'Hi'),
        new StreamChunk(ChunkType::Done, finishReason: FinishReason::Length),
    ]);

    foreach ($stream as $chunk) {
        // consume
    }

    expect($stream->getFinishReason())->toBe(FinishReason::Length);
});

it('accumulates tool calls across chunks', function () {
    $stream = new StreamResponse([
        new StreamChunk(ChunkType::ToolCall, toolCalls: [new ToolCall('tc-1', 'search', ['q' => 'a'])]),
        new StreamChunk(ChunkType::ToolCall, toolCalls: [new ToolCall('tc-2', 'calc', ['x' => 1])]),
        new StreamChunk(ChunkType::Done),
    ]);

    foreach ($stream as $chunk) {
        // consume
    }

    $toolCalls = $stream->getToolCalls();
    expect($toolCalls)->toHaveCount(2);
    expect($toolCalls[0]->name)->toBe('search');
    expect($toolCalls[1]->name)->toBe('calc');
});
