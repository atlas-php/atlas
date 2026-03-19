<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\StreamResponse;

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
