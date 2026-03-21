<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Requests\AudioRequest;
use Atlasphp\Atlas\Requests\EmbedRequest;
use Atlasphp\Atlas\Requests\ImageRequest;
use Atlasphp\Atlas\Requests\ModerateRequest;
use Atlasphp\Atlas\Requests\RerankRequest;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Requests\VideoRequest;
use Atlasphp\Atlas\Responses\AudioResponse;
use Atlasphp\Atlas\Responses\EmbeddingsResponse;
use Atlasphp\Atlas\Responses\ImageResponse;
use Atlasphp\Atlas\Responses\ModerationResponse;
use Atlasphp\Atlas\Responses\RerankResponse;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\VideoResponse;
use Atlasphp\Atlas\Testing\FakeDriver;
use Atlasphp\Atlas\Testing\ImageResponseFake;
use Atlasphp\Atlas\Testing\RerankResponseFake;
use Atlasphp\Atlas\Testing\StreamResponseFake;
use Atlasphp\Atlas\Testing\TextResponseFake;

function makeTestTextRequest(string $model = 'gpt-4o'): TextRequest
{
    return new TextRequest(
        model: $model,
        instructions: null,
        message: 'Hello',
        messageMedia: [],
        messages: [],
        maxTokens: null,
        temperature: null,
        schema: null,
        tools: [],
        providerTools: [],
        providerOptions: [],
    );
}

function makeTestImageRequest(string $model = 'dall-e-3'): ImageRequest
{
    return new ImageRequest(
        model: $model,
        instructions: 'A cat',
        media: [],
        size: null,
        quality: null,
        format: null,
    );
}

function makeTestAudioRequest(string $model = 'tts-1'): AudioRequest
{
    return new AudioRequest(
        model: $model,
        instructions: 'Say hello',
        media: [],
        voice: null,
        speed: null,
        language: null,
        duration: null,
        format: null,
        voiceClone: null,
    );
}

function makeTestVideoRequest(string $model = 'sora'): VideoRequest
{
    return new VideoRequest(
        model: $model,
        instructions: 'A sunset',
        media: [],
        duration: null,
        ratio: null,
        format: null,
    );
}

function makeTestEmbedRequest(string $model = 'text-embedding-3-small'): EmbedRequest
{
    return new EmbedRequest(
        model: $model,
        input: 'Hello world',
    );
}

function makeTestModerateRequest(string $model = 'text-moderation-latest'): ModerateRequest
{
    return new ModerateRequest(
        model: $model,
        input: 'Test content',
    );
}

// ─── Recording ───────────────────────────────────────────────────────────────

it('records text calls', function () {
    $driver = new FakeDriver('openai');

    $driver->text(makeTestTextRequest());

    expect($driver->recorded())->toHaveCount(1);
    expect($driver->recorded()[0]->method)->toBe('text');
    expect($driver->recorded()[0]->provider)->toBe('openai');
    expect($driver->recorded()[0]->model)->toBe('gpt-4o');
});

// ─── Sequencing ──────────────────────────────────────────────────────────────

it('returns responses from sequence in order', function () {
    $driver = new FakeDriver('openai', [
        TextResponseFake::make()->withText('first'),
        TextResponseFake::make()->withText('second'),
    ]);

    $first = $driver->text(makeTestTextRequest());
    $second = $driver->text(makeTestTextRequest());

    expect($first->text)->toBe('first');
    expect($second->text)->toBe('second');
});

it('repeats last response when sequence is exhausted', function () {
    $driver = new FakeDriver('openai', [
        TextResponseFake::make()->withText('first'),
        TextResponseFake::make()->withText('second'),
    ]);

    $driver->text(makeTestTextRequest());
    $driver->text(makeTestTextRequest());
    $third = $driver->text(makeTestTextRequest());

    expect($third->text)->toBe('second');
});

it('returns default response when no sequence provided', function () {
    $driver = new FakeDriver('openai');

    $response = $driver->text(makeTestTextRequest());

    expect($response)->toBeInstanceOf(TextResponse::class);
    expect($response->text)->toBe('');
});

// ─── All Modality Methods ────────────────────────────────────────────────────

it('records and returns for all modality methods', function () {
    $driver = new FakeDriver('openai');

    expect($driver->text(makeTestTextRequest()))->toBeInstanceOf(TextResponse::class);
    expect($driver->stream(makeTestTextRequest()))->toBeInstanceOf(StreamResponse::class);
    expect($driver->structured(makeTestTextRequest()))->toBeInstanceOf(StructuredResponse::class);
    expect($driver->image(makeTestImageRequest()))->toBeInstanceOf(ImageResponse::class);
    expect($driver->imageToText(makeTestImageRequest()))->toBeInstanceOf(TextResponse::class);
    expect($driver->audio(makeTestAudioRequest()))->toBeInstanceOf(AudioResponse::class);
    expect($driver->audioToText(makeTestAudioRequest()))->toBeInstanceOf(TextResponse::class);
    expect($driver->video(makeTestVideoRequest()))->toBeInstanceOf(VideoResponse::class);
    expect($driver->videoToText(makeTestVideoRequest()))->toBeInstanceOf(TextResponse::class);
    expect($driver->embed(makeTestEmbedRequest()))->toBeInstanceOf(EmbeddingsResponse::class);
    expect($driver->moderate(makeTestModerateRequest()))->toBeInstanceOf(ModerationResponse::class);

    $recorded = $driver->recorded();
    expect($recorded)->toHaveCount(11);

    $methods = array_map(fn ($r) => $r->method, $recorded);
    expect($methods)->toBe([
        'text', 'stream', 'structured', 'image', 'imageToText',
        'audio', 'audioToText', 'video', 'videoToText', 'embed', 'moderate',
    ]);
});

// ─── Capabilities ────────────────────────────────────────────────────────────

it('reports all capabilities as supported', function () {
    $driver = new FakeDriver('openai');
    $capabilities = $driver->capabilities();

    expect($capabilities->supports('text'))->toBeTrue();
    expect($capabilities->supports('stream'))->toBeTrue();
    expect($capabilities->supports('structured'))->toBeTrue();
    expect($capabilities->supports('image'))->toBeTrue();
    expect($capabilities->supports('imageToText'))->toBeTrue();
    expect($capabilities->supports('audio'))->toBeTrue();
    expect($capabilities->supports('audioToText'))->toBeTrue();
    expect($capabilities->supports('video'))->toBeTrue();
    expect($capabilities->supports('videoToText'))->toBeTrue();
    expect($capabilities->supports('embed'))->toBeTrue();
    expect($capabilities->supports('moderate'))->toBeTrue();
});

// ─── Stream Auto-Wrap ────────────────────────────────────────────────────────

it('auto-wraps TextResponseFake in StreamResponseFake for stream calls', function () {
    $driver = new FakeDriver('openai', [
        TextResponseFake::make()->withText('streamed text'),
    ]);

    $response = $driver->stream(makeTestTextRequest());

    expect($response)->toBeInstanceOf(StreamResponse::class);

    $chunks = iterator_to_array($response);
    $textChunks = array_filter($chunks, fn ($c) => $c->type === ChunkType::Text);
    $doneChunks = array_filter($chunks, fn ($c) => $c->type === ChunkType::Done);

    expect($textChunks)->not->toBeEmpty();
    expect($doneChunks)->toHaveCount(1);
    expect($response->getText())->toBe('streamed text');
});

it('uses StreamResponseFake directly when provided for stream calls', function () {
    $driver = new FakeDriver('openai', [
        StreamResponseFake::make()->withText('abcdef')->withChunkSize(3),
    ]);

    $response = $driver->stream(makeTestTextRequest());
    $chunks = iterator_to_array($response);
    $textChunks = array_values(array_filter($chunks, fn ($c) => $c->type === ChunkType::Text));

    expect($textChunks)->toHaveCount(2);
    expect($textChunks[0]->text)->toBe('abc');
    expect($textChunks[1]->text)->toBe('def');
});

// ─── Specific Fake Types in Sequence ─────────────────────────────────────────

it('returns correct response type from sequence for each modality', function () {
    $driver = new FakeDriver('openai', [
        ImageResponseFake::make()->withUrl('https://custom.url/img.png'),
    ]);

    $response = $driver->image(makeTestImageRequest());

    expect($response->url)->toBe('https://custom.url/img.png');
});

// ─── Name ────────────────────────────────────────────────────────────────────

it('returns the configured provider name', function () {
    $driver = new FakeDriver('my-custom-provider');

    expect($driver->name())->toBe('my-custom-provider');
});

it('defaults to configured provider name', function () {
    $driver = new FakeDriver('anthropic');

    expect($driver->name())->toBe('anthropic');
});

// ─── Rerank ──────────────────────────────────────────────────────────────────

it('returns RerankResponse from default sequence', function () {
    $driver = new FakeDriver('cohere');

    $request = new RerankRequest(
        model: 'rerank-v3',
        query: 'What is Laravel?',
        documents: ['Doc 1', 'Doc 2'],
    );

    $response = $driver->rerank($request);

    expect($response)->toBeInstanceOf(RerankResponse::class);
    expect($response->results)->not->toBeEmpty();
});

it('returns custom RerankResponse from sequence', function () {
    $driver = new FakeDriver('cohere', [
        RerankResponseFake::withCount(2, [0.9, 0.3]),
    ]);

    $request = new RerankRequest(
        model: 'rerank-v3',
        query: 'Test query',
        documents: ['Doc A', 'Doc B'],
    );

    $response = $driver->rerank($request);

    expect($response->results)->toHaveCount(2);
    expect($response->results[0]->score)->toBe(0.9);
    expect($response->results[1]->score)->toBe(0.3);
});

it('records rerank calls for assertions', function () {
    $driver = new FakeDriver('cohere');

    $request = new RerankRequest(
        model: 'rerank-v3',
        query: 'Test',
        documents: ['Doc'],
    );

    $driver->rerank($request);

    expect($driver->recorded('rerank'))->toHaveCount(1);
    expect($driver->recorded('rerank')[0]->method)->toBe('rerank');
    expect($driver->recorded('rerank')[0]->request->query)->toBe('Test');
});
