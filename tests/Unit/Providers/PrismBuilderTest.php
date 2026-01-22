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

test('it builds embeddings request with provider options', function () {
    $options = ['dimensions' => 256, 'encoding_format' => 'float'];

    $mockRequest = Mockery::mock(EmbeddingsPendingRequest::class);
    $mockRequest->shouldReceive('using')->andReturnSelf();
    $mockRequest->shouldReceive('fromInput')->with('test text')->andReturnSelf();
    $mockRequest->shouldReceive('withProviderOptions')->with($options)->once()->andReturnSelf();

    Prism::shouldReceive('embeddings')->andReturn($mockRequest);

    $request = $this->builder->forEmbeddings('openai', 'text-embedding-3-small', 'test text', $options);

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

test('it builds image request with provider options', function () {
    $options = ['size' => '1024x1024', 'quality' => 'hd', 'style' => 'vivid'];

    $mockRequest = Mockery::mock(ImagePendingRequest::class);
    $mockRequest->shouldReceive('using')->andReturnSelf();
    $mockRequest->shouldReceive('withPrompt')->with('A beautiful sunset')->andReturnSelf();
    $mockRequest->shouldReceive('withProviderOptions')->with($options)->once()->andReturnSelf();

    Prism::shouldReceive('image')->andReturn($mockRequest);

    $request = $this->builder->forImage('openai', 'dall-e-3', 'A beautiful sunset', $options);

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

test('it builds speech request with provider options', function () {
    $mockRequest = Mockery::mock(AudioPendingRequest::class);
    $mockRequest->shouldReceive('using')->andReturnSelf();
    $mockRequest->shouldReceive('withInput')->with('Hello world')->andReturnSelf();
    $mockRequest->shouldReceive('withProviderOptions')->with(['speed' => 1.5])->once()->andReturnSelf();

    Prism::shouldReceive('audio')->andReturn($mockRequest);

    $request = $this->builder->forSpeech('openai', 'tts-1', 'Hello world', ['speed' => 1.5]);

    expect($request)->toBeInstanceOf(AudioPendingRequest::class);
});

test('it builds speech request with voice and provider options', function () {
    $mockRequest = Mockery::mock(AudioPendingRequest::class);
    $mockRequest->shouldReceive('using')->andReturnSelf();
    $mockRequest->shouldReceive('withInput')->with('Hello world')->andReturnSelf();
    $mockRequest->shouldReceive('withVoice')->with('nova')->once()->andReturnSelf();
    $mockRequest->shouldReceive('withProviderOptions')->with(['speed' => 1.2])->once()->andReturnSelf();

    Prism::shouldReceive('audio')->andReturn($mockRequest);

    $request = $this->builder->forSpeech('openai', 'tts-1', 'Hello world', ['voice' => 'nova', 'speed' => 1.2]);

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

test('it builds transcription request with provider options', function () {
    $audio = Audio::fromBase64('dGVzdCBhdWRpbyBkYXRh', 'audio/mp3');
    $options = ['language' => 'en', 'prompt' => 'Transcribe the following'];

    $mockRequest = Mockery::mock(AudioPendingRequest::class);
    $mockRequest->shouldReceive('using')->andReturnSelf();
    $mockRequest->shouldReceive('withInput')->andReturnSelf();
    $mockRequest->shouldReceive('withProviderOptions')->with($options)->once()->andReturnSelf();

    Prism::shouldReceive('audio')->andReturn($mockRequest);

    $request = $this->builder->forTranscription('openai', 'whisper-1', $audio, $options);

    expect($request)->toBeInstanceOf(AudioPendingRequest::class);
});

test('it builds text request for single prompt', function () {
    $mockRequest = Mockery::mock(\Prism\Prism\Text\PendingRequest::class);
    $mockRequest->shouldReceive('using')->andReturnSelf();
    $mockRequest->shouldReceive('withSystemPrompt')->with('You are a helpful assistant.')->andReturnSelf();
    $mockRequest->shouldReceive('withPrompt')->with('Hello')->andReturnSelf();

    Prism::shouldReceive('text')->andReturn($mockRequest);

    $request = $this->builder->forPrompt('openai', 'gpt-4o', 'Hello', 'You are a helpful assistant.');

    expect($request)->toBeInstanceOf(\Prism\Prism\Text\PendingRequest::class);
});

test('it builds text request for prompt with tools', function () {
    $tools = [Mockery::mock(\Prism\Prism\Tool::class)];

    $mockRequest = Mockery::mock(\Prism\Prism\Text\PendingRequest::class);
    $mockRequest->shouldReceive('using')->andReturnSelf();
    $mockRequest->shouldReceive('withSystemPrompt')->with('You are a helpful assistant.')->andReturnSelf();
    $mockRequest->shouldReceive('withPrompt')->with('Hello')->andReturnSelf();
    $mockRequest->shouldReceive('withTools')->with($tools)->once()->andReturnSelf();

    Prism::shouldReceive('text')->andReturn($mockRequest);

    $request = $this->builder->forPrompt('openai', 'gpt-4o', 'Hello', 'You are a helpful assistant.', $tools);

    expect($request)->toBeInstanceOf(\Prism\Prism\Text\PendingRequest::class);
});

test('it builds text request for messages', function () {
    $messages = [
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi there!'],
    ];

    $mockRequest = Mockery::mock(\Prism\Prism\Text\PendingRequest::class);
    $mockRequest->shouldReceive('using')->andReturnSelf();
    $mockRequest->shouldReceive('withSystemPrompt')->with('You are a helpful assistant.')->andReturnSelf();
    $mockRequest->shouldReceive('withMessages')->with(Mockery::type('array'))->andReturnSelf();

    Prism::shouldReceive('text')->andReturn($mockRequest);

    $request = $this->builder->forMessages('openai', 'gpt-4o', $messages, 'You are a helpful assistant.');

    expect($request)->toBeInstanceOf(\Prism\Prism\Text\PendingRequest::class);
});

test('it builds text request for messages with tools', function () {
    $messages = [
        ['role' => 'user', 'content' => 'Hello'],
    ];
    $tools = [Mockery::mock(\Prism\Prism\Tool::class)];

    $mockRequest = Mockery::mock(\Prism\Prism\Text\PendingRequest::class);
    $mockRequest->shouldReceive('using')->andReturnSelf();
    $mockRequest->shouldReceive('withSystemPrompt')->with('You are a helpful assistant.')->andReturnSelf();
    $mockRequest->shouldReceive('withMessages')->with(Mockery::type('array'))->andReturnSelf();
    $mockRequest->shouldReceive('withTools')->with($tools)->once()->andReturnSelf();

    Prism::shouldReceive('text')->andReturn($mockRequest);

    $request = $this->builder->forMessages('openai', 'gpt-4o', $messages, 'You are a helpful assistant.', $tools);

    expect($request)->toBeInstanceOf(\Prism\Prism\Text\PendingRequest::class);
});

test('it builds structured output request', function () {
    $schema = Mockery::mock(\Prism\Prism\Contracts\Schema::class);

    $mockRequest = Mockery::mock(\Prism\Prism\Structured\PendingRequest::class);
    $mockRequest->shouldReceive('using')->andReturnSelf();
    $mockRequest->shouldReceive('withSystemPrompt')->with('You are a helpful assistant.')->andReturnSelf();
    $mockRequest->shouldReceive('withPrompt')->with('Extract the data')->andReturnSelf();
    $mockRequest->shouldReceive('withSchema')->with($schema)->andReturnSelf();

    Prism::shouldReceive('structured')->andReturn($mockRequest);

    $request = $this->builder->forStructured('openai', 'gpt-4o', $schema, 'Extract the data', 'You are a helpful assistant.');

    expect($request)->toBeInstanceOf(\Prism\Prism\Structured\PendingRequest::class);
});

test('it converts user messages correctly', function () {
    $messages = [
        ['role' => 'user', 'content' => 'Hello'],
    ];

    $convertedMessages = null;
    $mockRequest = Mockery::mock(\Prism\Prism\Text\PendingRequest::class);
    $mockRequest->shouldReceive('using')->andReturnSelf();
    $mockRequest->shouldReceive('withSystemPrompt')->andReturnSelf();
    $mockRequest->shouldReceive('withMessages')->with(Mockery::on(function ($converted) use (&$convertedMessages) {
        $convertedMessages = $converted;

        return true;
    }))->andReturnSelf();

    Prism::shouldReceive('text')->andReturn($mockRequest);

    $this->builder->forMessages('openai', 'gpt-4o', $messages, 'System prompt');

    expect($convertedMessages)->toHaveCount(1);
    expect($convertedMessages[0])->toBeInstanceOf(\Prism\Prism\ValueObjects\Messages\UserMessage::class);
});

test('it converts assistant messages correctly', function () {
    $messages = [
        ['role' => 'assistant', 'content' => 'Hello, how can I help?'],
    ];

    $convertedMessages = null;
    $mockRequest = Mockery::mock(\Prism\Prism\Text\PendingRequest::class);
    $mockRequest->shouldReceive('using')->andReturnSelf();
    $mockRequest->shouldReceive('withSystemPrompt')->andReturnSelf();
    $mockRequest->shouldReceive('withMessages')->with(Mockery::on(function ($converted) use (&$convertedMessages) {
        $convertedMessages = $converted;

        return true;
    }))->andReturnSelf();

    Prism::shouldReceive('text')->andReturn($mockRequest);

    $this->builder->forMessages('openai', 'gpt-4o', $messages, 'System prompt');

    expect($convertedMessages)->toHaveCount(1);
    expect($convertedMessages[0])->toBeInstanceOf(\Prism\Prism\ValueObjects\Messages\AssistantMessage::class);
});

test('it converts system messages correctly', function () {
    $messages = [
        ['role' => 'system', 'content' => 'Additional system context'],
    ];

    $convertedMessages = null;
    $mockRequest = Mockery::mock(\Prism\Prism\Text\PendingRequest::class);
    $mockRequest->shouldReceive('using')->andReturnSelf();
    $mockRequest->shouldReceive('withSystemPrompt')->andReturnSelf();
    $mockRequest->shouldReceive('withMessages')->with(Mockery::on(function ($converted) use (&$convertedMessages) {
        $convertedMessages = $converted;

        return true;
    }))->andReturnSelf();

    Prism::shouldReceive('text')->andReturn($mockRequest);

    $this->builder->forMessages('openai', 'gpt-4o', $messages, 'System prompt');

    expect($convertedMessages)->toHaveCount(1);
    expect($convertedMessages[0])->toBeInstanceOf(\Prism\Prism\ValueObjects\Messages\SystemMessage::class);
});

test('it converts mixed messages correctly', function () {
    $messages = [
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi!'],
        ['role' => 'user', 'content' => 'How are you?'],
    ];

    $convertedMessages = null;
    $mockRequest = Mockery::mock(\Prism\Prism\Text\PendingRequest::class);
    $mockRequest->shouldReceive('using')->andReturnSelf();
    $mockRequest->shouldReceive('withSystemPrompt')->andReturnSelf();
    $mockRequest->shouldReceive('withMessages')->with(Mockery::on(function ($converted) use (&$convertedMessages) {
        $convertedMessages = $converted;

        return true;
    }))->andReturnSelf();

    Prism::shouldReceive('text')->andReturn($mockRequest);

    $this->builder->forMessages('openai', 'gpt-4o', $messages, 'System prompt');

    expect($convertedMessages)->toHaveCount(3);
    expect($convertedMessages[0])->toBeInstanceOf(\Prism\Prism\ValueObjects\Messages\UserMessage::class);
    expect($convertedMessages[1])->toBeInstanceOf(\Prism\Prism\ValueObjects\Messages\AssistantMessage::class);
    expect($convertedMessages[2])->toBeInstanceOf(\Prism\Prism\ValueObjects\Messages\UserMessage::class);
});

test('it throws exception for unknown message role', function () {
    $messages = [
        ['role' => 'unknown', 'content' => 'Hello'],
    ];

    $mockRequest = Mockery::mock(\Prism\Prism\Text\PendingRequest::class);
    $mockRequest->shouldReceive('using')->andReturnSelf();
    $mockRequest->shouldReceive('withSystemPrompt')->andReturnSelf();

    Prism::shouldReceive('text')->andReturn($mockRequest);

    expect(fn () => $this->builder->forMessages('openai', 'gpt-4o', $messages, 'System prompt'))
        ->toThrow(\InvalidArgumentException::class, 'Unknown message role: unknown. Valid roles are: user, assistant, system.');
});
