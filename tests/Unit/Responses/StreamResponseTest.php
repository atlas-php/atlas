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

// ─── Finally callbacks ──────────────────────────────────────────────────────

it('fires finally callbacks in order after a successful stream', function () {
    $order = [];

    $stream = new StreamResponse([new StreamChunk(ChunkType::Done)]);
    $stream->onFinally(function () use (&$order) {
        $order[] = 'a';
    })->onFinally(function () use (&$order) {
        $order[] = 'b';
    });

    foreach ($stream as $chunk) {
        // consume
    }

    expect($order)->toBe(['a', 'b']);
});

it('runs every finally callback even when one throws', function () {
    $order = [];

    $stream = new StreamResponse([new StreamChunk(ChunkType::Done)]);
    $stream->onFinally(function () use (&$order) {
        $order[] = 'a';
    })->onFinally(function () use (&$order) {
        $order[] = 'b';

        throw new RuntimeException('finally blew up');
    })->onFinally(function () use (&$order) {
        $order[] = 'c';
    });

    // The throwing callback must neither abort the others nor surface from iteration.
    foreach ($stream as $chunk) {
        // consume
    }

    expect($order)->toBe(['a', 'b', 'c']);
});

it('fires finally callbacks when the stream errors and still rethrows', function () {
    $fired = false;

    $source = (function () {
        yield new StreamChunk(ChunkType::Text, text: 'Hi');

        throw new RuntimeException('stream blew up');
    })();

    $stream = new StreamResponse($source);
    $stream->onFinally(function () use (&$fired) {
        $fired = true;
    });

    expect(function () use ($stream) {
        foreach ($stream as $chunk) {
            // consume until the source throws
        }
    })->toThrow(RuntimeException::class, 'stream blew up');

    expect($fired)->toBeTrue();
});

it('still runs later finally callbacks when an earlier one throws on the error path', function () {
    $order = [];

    $source = (function () {
        yield new StreamChunk(ChunkType::Text, text: 'Hi');

        throw new RuntimeException('stream blew up');
    })();

    $stream = new StreamResponse($source);
    $stream->onFinally(function () use (&$order) {
        $order[] = 'a';

        throw new RuntimeException('finally blew up');
    })->onFinally(function () use (&$order) {
        $order[] = 'b';
    });

    expect(function () use ($stream) {
        foreach ($stream as $chunk) {
            // consume
        }
    })->toThrow(RuntimeException::class, 'stream blew up');

    expect($order)->toBe(['a', 'b']);
});
