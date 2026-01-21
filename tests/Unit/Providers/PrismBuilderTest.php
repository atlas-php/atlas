<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Services\PrismBuilder;
use Prism\Prism\Audio\PendingRequest as AudioPendingRequest;
use Prism\Prism\Embeddings\PendingRequest as EmbeddingsPendingRequest;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Images\PendingRequest as ImagePendingRequest;
use Prism\Prism\ValueObjects\Media\Audio;

beforeEach(function () {
    $this->builder = new PrismBuilder;
});

test('it builds embeddings request for single input', function () {
    $mockRequest = Mockery::mock(EmbeddingsPendingRequest::class);
    $mockRequest->shouldReceive('using')->andReturnSelf();
    $mockRequest->shouldReceive('fromInput')->with('test text')->andReturnSelf();

    Prism::shouldReceive('embeddings')->andReturn($mockRequest);

    $request = $this->builder->forEmbeddings('openai', 'text-embedding-3-small', 'test text');

    expect($request)->toBeInstanceOf(EmbeddingsPendingRequest::class);
});

test('it builds embeddings request for array input', function () {
    $mockRequest = Mockery::mock(EmbeddingsPendingRequest::class);
    $mockRequest->shouldReceive('using')->andReturnSelf();
    $mockRequest->shouldReceive('fromArray')->with(['text 1', 'text 2'])->andReturnSelf();

    Prism::shouldReceive('embeddings')->andReturn($mockRequest);

    $request = $this->builder->forEmbeddings('openai', 'text-embedding-3-small', ['text 1', 'text 2']);

    expect($request)->toBeInstanceOf(EmbeddingsPendingRequest::class);
});

test('it builds image request', function () {
    $mockRequest = Mockery::mock(ImagePendingRequest::class);
    $mockRequest->shouldReceive('using')->andReturnSelf();
    $mockRequest->shouldReceive('withPrompt')->with('A beautiful sunset')->andReturnSelf();

    Prism::shouldReceive('image')->andReturn($mockRequest);

    $request = $this->builder->forImage('openai', 'dall-e-3', 'A beautiful sunset');

    expect($request)->toBeInstanceOf(ImagePendingRequest::class);
});

test('it builds speech request', function () {
    $mockRequest = Mockery::mock(AudioPendingRequest::class);
    $mockRequest->shouldReceive('using')->andReturnSelf();
    $mockRequest->shouldReceive('withInput')->with('Hello world')->andReturnSelf();

    Prism::shouldReceive('audio')->andReturn($mockRequest);

    $request = $this->builder->forSpeech('openai', 'tts-1', 'Hello world');

    expect($request)->toBeInstanceOf(AudioPendingRequest::class);
});

test('it builds speech request with voice option', function () {
    $mockRequest = Mockery::mock(AudioPendingRequest::class);
    $mockRequest->shouldReceive('using')->andReturnSelf();
    $mockRequest->shouldReceive('withInput')->with('Hello world')->andReturnSelf();
    $mockRequest->shouldReceive('withVoice')->with('alloy')->andReturnSelf();

    Prism::shouldReceive('audio')->andReturn($mockRequest);

    $request = $this->builder->forSpeech('openai', 'tts-1', 'Hello world', ['voice' => 'alloy']);

    expect($request)->toBeInstanceOf(AudioPendingRequest::class);
});

test('it builds transcription request', function () {
    $audio = Audio::fromBase64('dGVzdCBhdWRpbyBkYXRh', 'audio/mp3');

    $mockRequest = Mockery::mock(AudioPendingRequest::class);
    $mockRequest->shouldReceive('using')->andReturnSelf();
    $mockRequest->shouldReceive('withInput')->andReturnSelf();

    Prism::shouldReceive('audio')->andReturn($mockRequest);

    $request = $this->builder->forTranscription('openai', 'whisper-1', $audio);

    expect($request)->toBeInstanceOf(AudioPendingRequest::class);
});
