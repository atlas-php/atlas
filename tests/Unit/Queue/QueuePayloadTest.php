<?php

declare(strict_types=1);

use Atlasphp\Atlas\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Pending\AudioRequest;
use Atlasphp\Atlas\Pending\EmbedRequest;
use Atlasphp\Atlas\Pending\ImageRequest;
use Atlasphp\Atlas\Pending\ModerateRequest;
use Atlasphp\Atlas\Pending\RerankRequest;
use Atlasphp\Atlas\Pending\TextRequest;
use Atlasphp\Atlas\Pending\VideoRequest;
use Atlasphp\Atlas\Responses\AudioResponse;
use Atlasphp\Atlas\Responses\EmbeddingsResponse;
use Atlasphp\Atlas\Responses\ImageResponse;
use Atlasphp\Atlas\Responses\ModerationResponse;
use Atlasphp\Atlas\Responses\RerankResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\VideoResponse;

// ─── TextRequest ────────────────────────────────────────────────────────────

it('TextRequest toQueuePayload serializes all properties', function () {
    $registry = app(ProviderRegistryContract::class);
    $request = new TextRequest('openai', 'gpt-5', $registry);
    $request->instructions('Be helpful');
    $request->message('Hello');
    $request->withMaxTokens(100);
    $request->withTemperature(0.7);

    $payload = $request->toQueuePayload();

    expect($payload['provider'])->toBe('openai')
        ->and($payload['model'])->toBe('gpt-5')
        ->and($payload['instructions'])->toBe('Be helpful')
        ->and($payload['message'])->toBe('Hello')
        ->and($payload['messageMedia'])->toBe([])
        ->and($payload['messages'])->toBe([])
        ->and($payload['maxTokens'])->toBe(100)
        ->and($payload['temperature'])->toBe(0.7)
        ->and($payload['tools'])->toBe([])
        ->and($payload['providerTools'])->toBe([])
        ->and($payload['maxSteps'])->toBe(200)
        ->and($payload['parallelToolCalls'])->toBeTrue()
        ->and($payload['schema'])->toBeNull()
        ->and($payload['providerOptions'])->toBe([])
        ->and($payload['meta'])->toBe([]);
});

it('TextRequest toQueuePayload serializes tools as class strings', function () {
    $registry = app(ProviderRegistryContract::class);
    $request = new TextRequest('openai', 'gpt-5', $registry);
    $request->message('Hello');
    $request->withTools(['App\\Tools\\FakeTool']);

    $payload = $request->toQueuePayload();

    expect($payload['tools'])->toBe(['App\\Tools\\FakeTool']);
});

it('TextRequest executeFromPayload rebuilds and executes asText', function () {
    Atlas::fake();

    $result = TextRequest::executeFromPayload(
        payload: [
            'provider' => 'openai',
            'model' => 'gpt-5',
            'instructions' => 'Be helpful',
            'message' => 'Hello',
            'messageMedia' => [],
            'messages' => [],
            'maxTokens' => null,
            'temperature' => null,
            'tools' => [],
            'providerTools' => [],
            'maxSteps' => null,
            'parallelToolCalls' => true,
            'schema' => null,
            'providerOptions' => [],
            'meta' => [],
        ],
        terminal: 'asText',
    );

    expect($result)->toBeInstanceOf(TextResponse::class);
});

it('TextRequest executeFromPayload rebuilds and executes asStructured', function () {
    Atlas::fake();

    $result = TextRequest::executeFromPayload(
        payload: [
            'provider' => 'openai',
            'model' => 'gpt-5',
            'instructions' => null,
            'message' => 'Hello',
            'messageMedia' => [],
            'messages' => [],
            'maxTokens' => null,
            'temperature' => null,
            'tools' => [],
            'providerTools' => [],
            'maxSteps' => null,
            'parallelToolCalls' => true,
            'schema' => null,
            'providerOptions' => [],
            'meta' => [],
        ],
        terminal: 'asStructured',
    );

    expect($result)->toBeInstanceOf(StructuredResponse::class);
});

it('TextRequest executeFromPayload throws on unknown terminal', function () {
    Atlas::fake();

    TextRequest::executeFromPayload(
        payload: [
            'provider' => 'openai',
            'model' => 'gpt-5',
            'instructions' => null,
            'message' => null,
            'messageMedia' => [],
            'messages' => [],
            'maxTokens' => null,
            'temperature' => null,
            'tools' => [],
            'providerTools' => [],
            'maxSteps' => null,
            'parallelToolCalls' => true,
            'schema' => null,
            'providerOptions' => [],
            'meta' => [],
        ],
        terminal: 'asUnknown',
    );
})->throws(InvalidArgumentException::class, 'Unknown terminal method: asUnknown');

// ─── ImageRequest ───────────────────────────────────────────────────────────

it('ImageRequest toQueuePayload serializes all properties', function () {
    $registry = app(ProviderRegistryContract::class);
    $request = new ImageRequest('openai', 'dall-e-3', $registry);
    $request->instructions('A sunset');
    $request->withSize('1024x1024');
    $request->withQuality('hd');
    $request->withFormat('png');
    $request->withCount(2);

    $payload = $request->toQueuePayload();

    expect($payload['provider'])->toBe('openai')
        ->and($payload['model'])->toBe('dall-e-3')
        ->and($payload['instructions'])->toBe('A sunset')
        ->and($payload['media'])->toBe([])
        ->and($payload['size'])->toBe('1024x1024')
        ->and($payload['quality'])->toBe('hd')
        ->and($payload['format'])->toBe('png')
        ->and($payload['count'])->toBe(2)
        ->and($payload['providerOptions'])->toBe([])
        ->and($payload['meta'])->toBe([]);
});

it('ImageRequest executeFromPayload rebuilds and executes asImage', function () {
    Atlas::fake();

    $result = ImageRequest::executeFromPayload(
        payload: [
            'provider' => 'openai',
            'model' => 'dall-e-3',
            'instructions' => 'A sunset',
            'media' => [],
            'size' => '1024x1024',
            'quality' => 'hd',
            'format' => 'png',
            'count' => 1,
            'providerOptions' => [],
            'meta' => [],
        ],
        terminal: 'asImage',
    );

    expect($result)->toBeInstanceOf(ImageResponse::class);
});

it('ImageRequest executeFromPayload rebuilds and executes asText', function () {
    Atlas::fake();

    $result = ImageRequest::executeFromPayload(
        payload: [
            'provider' => 'openai',
            'model' => 'gpt-5',
            'instructions' => 'Describe this',
            'media' => [],
            'size' => null,
            'quality' => null,
            'format' => null,
            'count' => 1,
            'providerOptions' => [],
            'meta' => [],
        ],
        terminal: 'asText',
    );

    expect($result)->toBeInstanceOf(TextResponse::class);
});

// ─── AudioRequest ───────────────────────────────────────────────────────────

it('AudioRequest toQueuePayload serializes all properties', function () {
    $registry = app(ProviderRegistryContract::class);
    $request = new AudioRequest('openai', 'tts-1', $registry);
    $request->instructions('Say hello');
    $request->withVoice('alloy');
    $request->withSpeed(1.5);
    $request->withLanguage('en');
    $request->withDuration(30);
    $request->withFormat('mp3');

    $payload = $request->toQueuePayload();

    expect($payload['provider'])->toBe('openai')
        ->and($payload['model'])->toBe('tts-1')
        ->and($payload['instructions'])->toBe('Say hello')
        ->and($payload['media'])->toBe([])
        ->and($payload['voice'])->toBe('alloy')
        ->and($payload['voiceClone'])->toBeNull()
        ->and($payload['speed'])->toBe(1.5)
        ->and($payload['language'])->toBe('en')
        ->and($payload['duration'])->toBe(30)
        ->and($payload['format'])->toBe('mp3')
        ->and($payload['providerOptions'])->toBe([])
        ->and($payload['meta'])->toBe([]);
});

it('AudioRequest executeFromPayload rebuilds and executes asAudio', function () {
    Atlas::fake();

    $result = AudioRequest::executeFromPayload(
        payload: [
            'provider' => 'openai',
            'model' => 'tts-1',
            'instructions' => 'Say hello',
            'media' => [],
            'voice' => 'alloy',
            'voiceClone' => null,
            'speed' => null,
            'language' => null,
            'duration' => null,
            'format' => null,
            'providerOptions' => [],
            'meta' => [],
        ],
        terminal: 'asAudio',
    );

    expect($result)->toBeInstanceOf(AudioResponse::class);
});

it('AudioRequest executeFromPayload rebuilds and executes asText', function () {
    Atlas::fake();

    $result = AudioRequest::executeFromPayload(
        payload: [
            'provider' => 'openai',
            'model' => 'whisper-1',
            'instructions' => 'Transcribe',
            'media' => [],
            'voice' => null,
            'voiceClone' => null,
            'speed' => null,
            'language' => null,
            'duration' => null,
            'format' => null,
            'providerOptions' => [],
            'meta' => [],
        ],
        terminal: 'asText',
    );

    expect($result)->toBeInstanceOf(TextResponse::class);
});

// ─── VideoRequest ───────────────────────────────────────────────────────────

it('VideoRequest toQueuePayload serializes all properties', function () {
    $registry = app(ProviderRegistryContract::class);
    $request = new VideoRequest('openai', 'sora', $registry);
    $request->instructions('Sunset timelapse');
    $request->withDuration(10);
    $request->withRatio('16:9');
    $request->withFormat('mp4');

    $payload = $request->toQueuePayload();

    expect($payload['provider'])->toBe('openai')
        ->and($payload['model'])->toBe('sora')
        ->and($payload['instructions'])->toBe('Sunset timelapse')
        ->and($payload['media'])->toBe([])
        ->and($payload['duration'])->toBe(10)
        ->and($payload['ratio'])->toBe('16:9')
        ->and($payload['format'])->toBe('mp4')
        ->and($payload['providerOptions'])->toBe([])
        ->and($payload['meta'])->toBe([]);
});

it('VideoRequest executeFromPayload rebuilds and executes asVideo', function () {
    Atlas::fake();

    $result = VideoRequest::executeFromPayload(
        payload: [
            'provider' => 'openai',
            'model' => 'sora',
            'instructions' => 'Sunset timelapse',
            'media' => [],
            'duration' => 10,
            'ratio' => '16:9',
            'format' => 'mp4',
            'providerOptions' => [],
            'meta' => [],
        ],
        terminal: 'asVideo',
    );

    expect($result)->toBeInstanceOf(VideoResponse::class);
});

it('VideoRequest executeFromPayload rebuilds and executes asText', function () {
    Atlas::fake();

    $result = VideoRequest::executeFromPayload(
        payload: [
            'provider' => 'openai',
            'model' => 'gpt-5',
            'instructions' => 'Describe this video',
            'media' => [],
            'duration' => null,
            'ratio' => null,
            'format' => null,
            'providerOptions' => [],
            'meta' => [],
        ],
        terminal: 'asText',
    );

    expect($result)->toBeInstanceOf(TextResponse::class);
});

// ─── EmbedRequest ───────────────────────────────────────────────────────────

it('EmbedRequest toQueuePayload serializes all properties', function () {
    $registry = app(ProviderRegistryContract::class);
    $request = new EmbedRequest('openai', 'text-embedding-3-small', $registry);
    $request->fromInput('Hello world');

    $payload = $request->toQueuePayload();

    expect($payload['provider'])->toBe('openai')
        ->and($payload['model'])->toBe('text-embedding-3-small')
        ->and($payload['input'])->toBe('Hello world')
        ->and($payload['providerOptions'])->toBe([])
        ->and($payload['meta'])->toBe([]);
});

it('EmbedRequest executeFromPayload rebuilds and executes asEmbeddings', function () {
    Atlas::fake();

    $result = EmbedRequest::executeFromPayload(
        payload: [
            'provider' => 'openai',
            'model' => 'text-embedding-3-small',
            'input' => 'Hello world',
            'providerOptions' => [],
            'meta' => [],
        ],
        terminal: 'asEmbeddings',
    );

    expect($result)->toBeInstanceOf(EmbeddingsResponse::class);
});

// ─── ModerateRequest ────────────────────────────────────────────────────────

it('ModerateRequest toQueuePayload serializes all properties', function () {
    $registry = app(ProviderRegistryContract::class);
    $request = new ModerateRequest('openai', 'omni-moderation', $registry);
    $request->fromInput('test content');

    $payload = $request->toQueuePayload();

    expect($payload['provider'])->toBe('openai')
        ->and($payload['model'])->toBe('omni-moderation')
        ->and($payload['input'])->toBe('test content')
        ->and($payload['providerOptions'])->toBe([])
        ->and($payload['meta'])->toBe([]);
});

it('ModerateRequest executeFromPayload rebuilds and executes asModeration', function () {
    Atlas::fake();

    $result = ModerateRequest::executeFromPayload(
        payload: [
            'provider' => 'openai',
            'model' => 'omni-moderation',
            'input' => 'test content',
            'providerOptions' => [],
            'meta' => [],
        ],
        terminal: 'asModeration',
    );

    expect($result)->toBeInstanceOf(ModerationResponse::class);
});

// ─── RerankRequest ──────────────────────────────────────────────────────────

it('RerankRequest toQueuePayload serializes all properties', function () {
    $registry = app(ProviderRegistryContract::class);
    $request = new RerankRequest('openai', 'rerank-v3', $registry);
    $request->query('test query');
    $request->documents(['doc1', 'doc2']);
    $request->topN(5);
    $request->maxTokensPerDoc(512);
    $request->minScore(0.5);

    $payload = $request->toQueuePayload();

    expect($payload['provider'])->toBe('openai')
        ->and($payload['model'])->toBe('rerank-v3')
        ->and($payload['query'])->toBe('test query')
        ->and($payload['documents'])->toBe(['doc1', 'doc2'])
        ->and($payload['topN'])->toBe(5)
        ->and($payload['maxTokensPerDoc'])->toBe(512)
        ->and($payload['minScore'])->toBe(0.5)
        ->and($payload['providerOptions'])->toBe([])
        ->and($payload['meta'])->toBe([]);
});

it('RerankRequest executeFromPayload rebuilds and executes asReranked', function () {
    Atlas::fake();

    $result = RerankRequest::executeFromPayload(
        payload: [
            'provider' => 'openai',
            'model' => 'rerank-v3',
            'query' => 'test query',
            'documents' => ['doc1', 'doc2'],
            'topN' => null,
            'maxTokensPerDoc' => null,
            'minScore' => null,
            'providerOptions' => [],
            'meta' => [],
        ],
        terminal: 'asReranked',
    );

    expect($result)->toBeInstanceOf(RerankResponse::class);
});
