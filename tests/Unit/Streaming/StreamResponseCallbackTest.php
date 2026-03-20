<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\Usage;

function makeTestStream(): StreamResponse
{
    return new StreamResponse((function () {
        yield new StreamChunk(ChunkType::Text, text: 'Hello');
        yield new StreamChunk(ChunkType::Text, text: ' World');
        yield new StreamChunk(ChunkType::Done, usage: new Usage(10, 5), finishReason: FinishReason::Stop);
    })());
}

it('onChunk fires for every chunk', function () {
    $chunks = [];

    $stream = makeTestStream()
        ->onChunk(function (StreamChunk $chunk) use (&$chunks) {
            $chunks[] = $chunk->type;
        });

    foreach ($stream as $chunk) {
        // consume
    }

    expect($chunks)->toBe([ChunkType::Text, ChunkType::Text, ChunkType::Done]);
});

it('onChunk receives the StreamChunk object', function () {
    $receivedTexts = [];

    $stream = makeTestStream()
        ->onChunk(function (StreamChunk $chunk) use (&$receivedTexts) {
            if ($chunk->text !== null) {
                $receivedTexts[] = $chunk->text;
            }
        });

    foreach ($stream as $chunk) {
        // consume
    }

    expect($receivedTexts)->toBe(['Hello', ' World']);
});

it('then fires once after stream completes', function () {
    $thenCalled = 0;
    $receivedText = null;

    $stream = makeTestStream()
        ->then(function (StreamResponse $s) use (&$thenCalled, &$receivedText) {
            $thenCalled++;
            $receivedText = $s->getText();
        });

    foreach ($stream as $chunk) {
        // consume
    }

    expect($thenCalled)->toBe(1);
    expect($receivedText)->toBe('Hello World');
});

it('then receives StreamResponse with accumulated data', function () {
    $receivedUsage = null;
    $receivedFinishReason = null;

    $stream = makeTestStream()
        ->then(function (StreamResponse $s) use (&$receivedUsage, &$receivedFinishReason) {
            $receivedUsage = $s->getUsage();
            $receivedFinishReason = $s->getFinishReason();
        });

    foreach ($stream as $chunk) {
        // consume
    }

    expect($receivedUsage)->not->toBeNull();
    expect($receivedUsage->inputTokens)->toBe(10);
    expect($receivedFinishReason)->toBe(FinishReason::Stop);
});

it('both onChunk and then work together', function () {
    $chunkCount = 0;
    $thenText = null;

    $stream = makeTestStream()
        ->onChunk(function () use (&$chunkCount) {
            $chunkCount++;
        })
        ->then(function (StreamResponse $s) use (&$thenText) {
            $thenText = $s->getText();
        });

    foreach ($stream as $chunk) {
        // consume
    }

    expect($chunkCount)->toBe(3);
    expect($thenText)->toBe('Hello World');
});

it('onChunk returns $this for chaining', function () {
    $stream = new StreamResponse((function () {
        yield from [];
    })());

    $result = $stream->onChunk(fn () => null);

    expect($result)->toBe($stream);
});

it('then returns $this for chaining', function () {
    $stream = new StreamResponse((function () {
        yield from [];
    })());

    $result = $stream->then(fn () => null);

    expect($result)->toBe($stream);
});
