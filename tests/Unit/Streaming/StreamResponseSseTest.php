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
    expect($output)->toContain('"input_tokens":10');
    expect($output)->toContain('"output_tokens":5');
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
