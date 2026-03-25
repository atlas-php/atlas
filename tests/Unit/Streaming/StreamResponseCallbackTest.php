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

it('multiple then callbacks fire in order', function () {
    $stream = new StreamResponse((function () {
        yield new StreamChunk(ChunkType::Text, text: 'Hello');
        yield new StreamChunk(ChunkType::Done);
    })());

    $order = [];

    $stream->then(function () use (&$order) {
        $order[] = 'first';
    });

    $stream->then(function () use (&$order) {
        $order[] = 'second';
    });

    foreach ($stream as $chunk) {
        // consume
    }

    expect($order)->toBe(['first', 'second']);
});

it('getReasoning returns accumulated reasoning content', function () {
    $chunks = (function () {
        yield new StreamChunk(type: ChunkType::Thinking, reasoning: 'Let me think');
        yield new StreamChunk(type: ChunkType::Thinking, reasoning: ' about this');
        yield new StreamChunk(type: ChunkType::Text, text: 'The answer is 42');
        yield new StreamChunk(type: ChunkType::Done, finishReason: FinishReason::Stop, usage: new Usage(10, 5));
    })();

    $stream = new StreamResponse($chunks);
    foreach ($stream as $chunk) { /* consume */
    }

    expect($stream->getReasoning())->toBe('Let me think about this');
    expect($stream->getText())->toBe('The answer is 42');
});

it('getReasoning returns empty string when no reasoning chunks', function () {
    $chunks = (function () {
        yield new StreamChunk(type: ChunkType::Text, text: 'No thinking here');
        yield new StreamChunk(type: ChunkType::Done, finishReason: FinishReason::Stop, usage: new Usage(5, 3));
    })();

    $stream = new StreamResponse($chunks);
    foreach ($stream as $chunk) { /* consume */
    }

    expect($stream->getReasoning())->toBe('');
});

// ─── onFinally ──────────────────────────────────────────────────────

it('onFinally fires after successful stream completion', function () {
    $finallyCalled = false;

    $stream = makeTestStream()
        ->onFinally(function () use (&$finallyCalled) {
            $finallyCalled = true;
        });

    foreach ($stream as $chunk) { /* consume */
    }

    expect($finallyCalled)->toBeTrue();
});

it('onFinally fires after stream error', function () {
    $finallyCalled = false;

    $stream = new StreamResponse((function () {
        yield new StreamChunk(ChunkType::Text, text: 'Hello');
        throw new RuntimeException('Stream error');
    })());

    $stream->onFinally(function () use (&$finallyCalled) {
        $finallyCalled = true;
    });

    try {
        foreach ($stream as $chunk) { /* consume */
        }
    } catch (RuntimeException) {
        // expected
    }

    expect($finallyCalled)->toBeTrue();
});

it('onFinally returns $this for chaining', function () {
    $stream = new StreamResponse((function () {
        yield from [];
    })());

    $result = $stream->onFinally(fn () => null);

    expect($result)->toBe($stream);
});

it('multiple onFinally callbacks fire in order', function () {
    $order = [];

    $stream = makeTestStream()
        ->onFinally(function () use (&$order) {
            $order[] = 'first';
        })
        ->onFinally(function () use (&$order) {
            $order[] = 'second';
        });

    foreach ($stream as $chunk) { /* consume */
    }

    expect($order)->toBe(['first', 'second']);
});

// ─── then() callback isolation ──────────────────────────────────────

it('then callback exception does not block subsequent callbacks', function () {
    $secondCalled = false;

    $stream = makeTestStream();
    $stream->then(function () {
        throw new RuntimeException('Callback error');
    });
    $stream->then(function () use (&$secondCalled) {
        $secondCalled = true;
    });

    foreach ($stream as $chunk) { /* consume */
    }

    expect($secondCalled)->toBeTrue();
});

it('then callback exception does not prevent onFinally', function () {
    $finallyCalled = false;

    $stream = makeTestStream();
    $stream->then(function () {
        throw new RuntimeException('Callback error');
    });
    $stream->onFinally(function () use (&$finallyCalled) {
        $finallyCalled = true;
    });

    foreach ($stream as $chunk) { /* consume */
    }

    expect($finallyCalled)->toBeTrue();
});
