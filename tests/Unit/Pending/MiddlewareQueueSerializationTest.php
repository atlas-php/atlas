<?php

declare(strict_types=1);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Middleware\Contracts\ProviderMiddleware;
use Atlasphp\Atlas\Middleware\ProviderContext;
use Atlasphp\Atlas\Pending\AudioRequest;
use Atlasphp\Atlas\Pending\EmbedRequest;
use Atlasphp\Atlas\Pending\ImageRequest;
use Atlasphp\Atlas\Pending\ModerateRequest;
use Atlasphp\Atlas\Pending\MusicRequest;
use Atlasphp\Atlas\Pending\RerankRequest;
use Atlasphp\Atlas\Pending\SfxRequest;
use Atlasphp\Atlas\Pending\SpeechRequest;
use Atlasphp\Atlas\Pending\TextRequest;
use Atlasphp\Atlas\Pending\VideoRequest;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Testing\AudioResponseFake;
use Atlasphp\Atlas\Testing\EmbeddingsResponseFake;
use Atlasphp\Atlas\Testing\ImageResponseFake;
use Atlasphp\Atlas\Testing\ModerationResponseFake;
use Atlasphp\Atlas\Testing\RerankResponseFake;
use Atlasphp\Atlas\Testing\VideoResponseFake;

/**
 * A real, container-instantiable middleware so the round-trip exercises actual
 * resolution on the worker side (the serialized payload stores its class string).
 */
class QueueTraceMiddleware implements ProviderMiddleware
{
    public function handle(ProviderContext $context, Closure $next): mixed
    {
        return $next($context);
    }
}

it('serializes class-string middleware to class strings for the queue', function () {
    $pending = new TextRequest('openai', 'gpt-4o', app(ProviderRegistryContract::class));
    $pending->withMiddleware(['App\\Atlas\\Middleware\\Example']);

    $payload = $pending->toQueuePayload();

    expect($payload['middleware'])->toBe(['App\\Atlas\\Middleware\\Example']);
});

it('serializes object-instance middleware to its class string for the queue', function () {
    $instance = new class
    {
        public function handle(mixed $context, Closure $next): mixed
        {
            return $next($context);
        }
    };

    $pending = new TextRequest('openai', 'gpt-4o', app(ProviderRegistryContract::class));
    $pending->withMiddleware([$instance]);

    $payload = $pending->toQueuePayload();

    expect($payload['middleware'])->toBe([$instance::class]);
});

it('fails fast when closure middleware is queued instead of corrupting the payload', function () {
    $pending = new TextRequest('openai', 'gpt-4o', app(ProviderRegistryContract::class));
    $pending->withMiddleware([fn (mixed $context, Closure $next): mixed => $next($context)]);

    expect(fn () => $pending->toQueuePayload())
        ->toThrow(
            InvalidArgumentException::class,
            'Closure middleware cannot be queued. Use a class-based middleware for queued requests.',
        );
});

// ─── Per-request middleware survives the queue boundary on every modality ────
// Regression: previously only TextRequest/AgentRequest serialized middleware,
// so per-request withMiddleware() was silently dropped when a non-text request
// was queued. Each modality builder must now carry it in toQueuePayload().

dataset('non_text_queueable_builders', [
    'image' => [fn ($r) => new ImageRequest('openai', 'gpt-image-1', $r)],
    'audio' => [fn ($r) => new AudioRequest('openai', 'whisper-1', $r)],
    'video' => [fn ($r) => new VideoRequest('openai', 'sora-2', $r)],
    'embed' => [fn ($r) => new EmbedRequest('openai', 'text-embedding-3-small', $r)],
    'moderate' => [fn ($r) => new ModerateRequest('openai', 'omni-moderation-latest', $r)],
    'rerank' => [fn ($r) => new RerankRequest('cohere', 'rerank-v3.5', $r)],
    'speech' => [fn ($r) => new SpeechRequest('openai', 'gpt-4o-mini-tts', $r)],
    'music' => [fn ($r) => new MusicRequest('elevenlabs', 'music-v1', $r)],
    'sfx' => [fn ($r) => new SfxRequest('elevenlabs', 'sfx-v1', $r)],
]);

it('serializes per-request middleware into the queue payload', function (Closure $make) {
    $pending = $make(app(ProviderRegistryContract::class));
    $pending->withMiddleware(['App\\Atlas\\Middleware\\Example']);

    $payload = $pending->toQueuePayload();

    expect($payload['middleware'])->toBe(['App\\Atlas\\Middleware\\Example']);
})->with('non_text_queueable_builders');

// Full round trip: serialize via the builder's own toQueuePayload(), then rehydrate
// via executeFromPayload() and assert the per-request middleware reached the request
// the (faked) driver actually received. Covers the restore side for every modality.

dataset('queue_round_trips', [
    'image' => [
        fn () => (new ImageRequest('openai', 'gpt-image-1', app(ProviderRegistryContract::class)))->instructions('a cat'),
        'asImage',
        fn () => ImageResponseFake::make(),
    ],
    'audio' => [
        fn () => (new AudioRequest('openai', 'gpt-4o-mini-tts', app(ProviderRegistryContract::class)))->instructions('hello'),
        'asAudio',
        fn () => AudioResponseFake::make(),
    ],
    'video' => [
        fn () => (new VideoRequest('openai', 'sora-2', app(ProviderRegistryContract::class)))->instructions('a dog'),
        'asVideo',
        fn () => VideoResponseFake::make(),
    ],
    'speech' => [
        fn () => (new SpeechRequest('openai', 'gpt-4o-mini-tts', app(ProviderRegistryContract::class)))->instructions('hello'),
        'asAudio',
        fn () => AudioResponseFake::make(),
    ],
    'embed' => [
        fn () => (new EmbedRequest('openai', 'text-embedding-3-small', app(ProviderRegistryContract::class)))->fromInput('hello'),
        'asEmbeddings',
        fn () => EmbeddingsResponseFake::make(),
    ],
    'moderate' => [
        fn () => (new ModerateRequest('openai', 'omni-moderation-latest', app(ProviderRegistryContract::class)))->fromInput('hello'),
        'asModeration',
        fn () => ModerationResponseFake::make(),
    ],
    // rerank routes through a faked Provider enum value (openai): cohere/jina are
    // not Provider enum cases, so Atlas::fake() does not register fake drivers for
    // them. FakeDriver reports rerank capability and records the call.
    'rerank' => [
        fn () => (new RerankRequest('openai', 'rerank-v3.5', app(ProviderRegistryContract::class)))->query('q')->documents(['a', 'b']),
        'asReranked',
        fn () => RerankResponseFake::make(),
    ],
    'music' => [
        fn () => (new MusicRequest('elevenlabs', 'music-v1', app(ProviderRegistryContract::class)))->instructions('jazz'),
        'asAudio',
        fn () => AudioResponseFake::make(),
    ],
    'sfx' => [
        fn () => (new SfxRequest('elevenlabs', 'sfx-v1', app(ProviderRegistryContract::class)))->instructions('boom'),
        'asAudio',
        fn () => AudioResponseFake::make(),
    ],
]);

it('restores per-request middleware across the queue boundary onto the executed request', function (Closure $build, string $terminal, Closure $response) {
    $fake = Atlas::fake([$response()]);

    $pending = $build();
    $pending->withMiddleware([QueueTraceMiddleware::class]);

    $pending::executeFromPayload($pending->toQueuePayload(), $terminal);

    expect($fake->recorded()[0]->request->middleware)->toBe([QueueTraceMiddleware::class]);
})->with('queue_round_trips');
