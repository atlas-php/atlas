<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\Usage;
use Illuminate\Contracts\Support\Responsable;
use Symfony\Component\HttpFoundation\StreamedResponse;

it('toResponse returns StreamedResponse', function () {
    $stream = new StreamResponse((function () {
        yield new StreamChunk(ChunkType::Done);
    })());

    $response = $stream->toResponse(request());

    expect($response)->toBeInstanceOf(StreamedResponse::class);
    expect($response->getStatusCode())->toBe(200);
});

it('sets correct SSE headers', function () {
    $stream = new StreamResponse((function () {
        yield new StreamChunk(ChunkType::Done);
    })());

    $response = $stream->toResponse(request());

    expect($response->headers->get('Content-Type'))->toBe('text/event-stream');
    expect($response->headers->get('Cache-Control'))->toContain('no-cache');
    expect($response->headers->get('X-Accel-Buffering'))->toBe('no');
});

it('implements Responsable', function () {
    $stream = new StreamResponse((function () {
        yield new StreamChunk(ChunkType::Done);
    })());

    expect($stream)->toBeInstanceOf(Responsable::class);
});

it('sends text chunks as SSE chunk events', function () {
    $stream = new StreamResponse((function () {
        yield new StreamChunk(ChunkType::Text, text: 'Hello');
        yield new StreamChunk(ChunkType::Done, usage: new Usage(5, 3), finishReason: FinishReason::Stop);
    })());

    $response = $stream->toResponse(request());

    // Capture at a level deep enough that ob_flush doesn't escape
    ob_start();
    ob_start();
    $response->sendContent();
    ob_end_flush();
    $output = ob_get_clean();

    expect($output)->toContain('event: chunk');
    expect($output)->toContain('"type":"chunk"');
    expect($output)->toContain('"text":"Hello"');
});

it('sends done chunk as SSE done event with accumulated text and usage', function () {
    $stream = new StreamResponse((function () {
        yield new StreamChunk(ChunkType::Text, text: 'Hi');
        yield new StreamChunk(ChunkType::Done, usage: new Usage(10, 5), finishReason: FinishReason::Stop);
    })());

    $response = $stream->toResponse(request());

    ob_start();
    ob_start();
    $response->sendContent();
    ob_end_flush();
    $output = ob_get_clean();

    expect($output)->toContain('event: done');
    expect($output)->toContain('"type":"done"');
    expect($output)->toContain('"text":"Hi"');
    expect($output)->toContain('"inputTokens":10');
    expect($output)->toContain('"outputTokens":5');
});

it('emits thinking SSE event for Thinking chunks', function () {
    $stream = new StreamResponse((function () {
        yield new StreamChunk(ChunkType::Thinking, reasoning: 'Let me think...');
        yield new StreamChunk(ChunkType::Text, text: 'Answer');
        yield new StreamChunk(ChunkType::Done, usage: new Usage(5, 3), finishReason: FinishReason::Stop);
    })());

    $response = $stream->toResponse(request());

    ob_start();
    ob_start();
    $response->sendContent();
    ob_end_flush();
    $output = ob_get_clean();

    // Count SSE events — should be 3 (chunk + thinking + done)
    expect(substr_count($output, 'event: '))->toBe(3);
    expect($output)->toContain('event: chunk');
    expect($output)->toContain('event: thinking');
    expect($output)->toContain('Let me think...');
    expect($output)->toContain('event: done');
});

it('sends tool call chunks as SSE tool_call events', function () {
    $stream = new StreamResponse((function () {
        yield new StreamChunk(
            type: ChunkType::ToolCall,
            toolCalls: [new ToolCall('tc-1', 'search', ['q' => 'test'])],
        );
        yield new StreamChunk(ChunkType::Done);
    })());

    $response = $stream->toResponse(request());

    ob_start();
    ob_start();
    $response->sendContent();
    ob_end_flush();
    $output = ob_get_clean();

    expect($output)->toContain('event: tool_call');
    expect($output)->toContain('"type":"tool_call"');
    expect($output)->toContain('"name":"search"');
});

// ─── Orchestration SSE events ──────────────────────────────────────

it('sends step_started SSE event', function () {
    $stream = new StreamResponse((function () {
        yield new StreamChunk(type: ChunkType::StepStarted, stepNumber: 1);
        yield new StreamChunk(ChunkType::Done);
    })());

    $response = $stream->toResponse(request());

    ob_start();
    ob_start();
    $response->sendContent();
    ob_end_flush();
    $output = ob_get_clean();

    expect($output)->toContain('event: step_started')
        ->and($output)->toContain('"stepNumber":1');
});

it('sends step_completed SSE event', function () {
    $stream = new StreamResponse((function () {
        yield new StreamChunk(type: ChunkType::StepCompleted, stepNumber: 2);
        yield new StreamChunk(ChunkType::Done);
    })());

    $response = $stream->toResponse(request());

    ob_start();
    ob_start();
    $response->sendContent();
    ob_end_flush();
    $output = ob_get_clean();

    expect($output)->toContain('event: step_completed')
        ->and($output)->toContain('"stepNumber":2');
});

it('sends tool_call_started SSE event', function () {
    $stream = new StreamResponse((function () {
        yield new StreamChunk(type: ChunkType::ToolCallStarted, stepNumber: 1, toolName: 'web_search', toolCallId: 'tc-1');
        yield new StreamChunk(ChunkType::Done);
    })());

    $response = $stream->toResponse(request());

    ob_start();
    ob_start();
    $response->sendContent();
    ob_end_flush();
    $output = ob_get_clean();

    expect($output)->toContain('event: tool_call_started')
        ->and($output)->toContain('"toolName":"web_search"')
        ->and($output)->toContain('"toolCallId":"tc-1"');
});

it('sends tool_call_completed SSE event', function () {
    $stream = new StreamResponse((function () {
        yield new StreamChunk(type: ChunkType::ToolCallCompleted, stepNumber: 1, toolName: 'search', toolCallId: 'tc-1', toolContent: 'Found 5 results', toolError: false);
        yield new StreamChunk(ChunkType::Done);
    })());

    $response = $stream->toResponse(request());

    ob_start();
    ob_start();
    $response->sendContent();
    ob_end_flush();
    $output = ob_get_clean();

    expect($output)->toContain('event: tool_call_completed')
        ->and($output)->toContain('"result":"Found 5 results"');
});

it('sends tool_call_failed SSE event', function () {
    $stream = new StreamResponse((function () {
        yield new StreamChunk(type: ChunkType::ToolCallFailed, stepNumber: 1, toolName: 'fetch', toolCallId: 'tc-1', toolContent: 'Connection refused', toolError: true);
        yield new StreamChunk(ChunkType::Done);
    })());

    $response = $stream->toResponse(request());

    ob_start();
    ob_start();
    $response->sendContent();
    ob_end_flush();
    $output = ob_get_clean();

    expect($output)->toContain('event: tool_call_failed')
        ->and($output)->toContain('"error":"Connection refused"');
});
