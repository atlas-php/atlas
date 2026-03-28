<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasCache;
use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Providers\ElevenLabs\ElevenLabsDriver;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\AudioRequest;
use Atlasphp\Atlas\Requests\EmbedRequest;
use Atlasphp\Atlas\Requests\ImageRequest;
use Atlasphp\Atlas\Requests\ModerateRequest;
use Atlasphp\Atlas\Requests\RerankRequest;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Requests\VideoRequest;
use Atlasphp\Atlas\Requests\VoiceRequest;
use Illuminate\Support\Facades\Http;

function makeElevenLabsDriver(): ElevenLabsDriver
{
    return new ElevenLabsDriver(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.elevenlabs.io/v1']),
        http: app(HttpClient::class),
        cache: app(AtlasCache::class),
    );
}

function makeElevenLabsAudioRequest(array $overrides = []): AudioRequest
{
    return new AudioRequest(
        model: $overrides['model'] ?? 'eleven_multilingual_v2',
        instructions: $overrides['instructions'] ?? 'Hello world',
        media: $overrides['media'] ?? [],
        voice: $overrides['voice'] ?? null,
        speed: $overrides['speed'] ?? null,
        language: $overrides['language'] ?? null,
        duration: $overrides['duration'] ?? null,
        format: $overrides['format'] ?? null,
        voiceClone: $overrides['voiceClone'] ?? null,
        providerOptions: $overrides['providerOptions'] ?? [],
        meta: $overrides['meta'] ?? [],
    );
}

// ─── Identity ───────────────────────────────────────────────────────────────

it('returns elevenlabs as name', function () {
    expect(makeElevenLabsDriver()->name())->toBe('elevenlabs');
});

// ─── Capabilities ───────────────────────────────────────────────────────────

it('reports correct capabilities', function () {
    $cap = makeElevenLabsDriver()->capabilities();

    expect($cap->supports('audio'))->toBeTrue()
        ->and($cap->supports('audioToText'))->toBeTrue()
        ->and($cap->supports('voice'))->toBeTrue()
        ->and($cap->supports('models'))->toBeTrue()
        ->and($cap->supports('voices'))->toBeTrue()
        ->and($cap->supports('text'))->toBeFalse()
        ->and($cap->supports('stream'))->toBeFalse()
        ->and($cap->supports('structured'))->toBeFalse()
        ->and($cap->supports('image'))->toBeFalse()
        ->and($cap->supports('video'))->toBeFalse()
        ->and($cap->supports('embed'))->toBeFalse()
        ->and($cap->supports('moderate'))->toBeFalse()
        ->and($cap->supports('rerank'))->toBeFalse()
        ->and($cap->supports('toolCalling'))->toBeFalse();
});

// ─── Audio mode dispatch ────────────────────────────────────────────────────

it('routes default audio mode to TTS handler', function () {
    Http::fake([
        'api.elevenlabs.io/*' => Http::response('tts-audio-data'),
    ]);

    $response = makeElevenLabsDriver()->audio(makeElevenLabsAudioRequest());

    expect($response->data)->toBe(base64_encode('tts-audio-data'));

    Http::assertSent(fn ($r) => str_contains($r->url(), '/text-to-speech/'));
});

it('routes sfx mode to SFX handler', function () {
    Http::fake([
        'api.elevenlabs.io/*' => Http::response('sfx-audio-data'),
    ]);

    $response = makeElevenLabsDriver()->audio(
        makeElevenLabsAudioRequest(['meta' => ['_audio_mode' => 'sfx']])
    );

    expect($response->data)->toBe(base64_encode('sfx-audio-data'));

    Http::assertSent(fn ($r) => str_contains($r->url(), '/sound-generation'));
});

it('routes music mode to Music handler', function () {
    Http::fake([
        'api.elevenlabs.io/*' => Http::response('music-audio-data'),
    ]);

    $response = makeElevenLabsDriver()->audio(
        makeElevenLabsAudioRequest(['meta' => ['_audio_mode' => 'music']])
    );

    expect($response->data)->toBe(base64_encode('music-audio-data'));

    Http::assertSent(fn ($r) => str_contains($r->url(), '/music'));
});

// ─── Unsupported modalities ─────────────────────────────────────────────────

it('throws UnsupportedFeatureException for text', function () {
    makeElevenLabsDriver()->text(new TextRequest('model', null, null, [], [], null, null, null, [], [], []));
})->throws(UnsupportedFeatureException::class);

it('throws UnsupportedFeatureException for image', function () {
    makeElevenLabsDriver()->image(new ImageRequest('model', null, [], null, null, null));
})->throws(UnsupportedFeatureException::class);

it('throws UnsupportedFeatureException for video', function () {
    makeElevenLabsDriver()->video(new VideoRequest('model', null, [], null, null, null));
})->throws(UnsupportedFeatureException::class);

it('throws UnsupportedFeatureException for embed', function () {
    makeElevenLabsDriver()->embed(new EmbedRequest('model', 'text'));
})->throws(UnsupportedFeatureException::class);

it('throws UnsupportedFeatureException for moderate', function () {
    makeElevenLabsDriver()->moderate(new ModerateRequest('test'));
})->throws(UnsupportedFeatureException::class);

it('throws UnsupportedFeatureException for rerank', function () {
    makeElevenLabsDriver()->rerank(new RerankRequest('model', 'query', ['doc']));
})->throws(UnsupportedFeatureException::class);

// ─── Provider handler ───────────────────────────────────────────────────────

it('lists models via provider handler', function () {
    Http::fake([
        'api.elevenlabs.io/v1/models' => Http::response([
            ['model_id' => 'eleven_multilingual_v2', 'name' => 'Eleven Multilingual v2'],
            ['model_id' => 'eleven_flash_v2_5', 'name' => 'Eleven Flash v2.5'],
        ]),
    ]);

    $models = makeElevenLabsDriver()->models();

    expect($models->models)->toContain('eleven_multilingual_v2')
        ->and($models->models)->toContain('eleven_flash_v2_5');
});

it('lists voices via provider handler', function () {
    Http::fake([
        'api.elevenlabs.io/v1/voices' => Http::response([
            'voices' => [
                ['voice_id' => 'abc123', 'name' => 'Rachel'],
                ['voice_id' => 'def456', 'name' => 'Adam'],
            ],
        ]),
    ]);

    $voices = makeElevenLabsDriver()->voices();

    expect($voices->voices)->toContain('abc123')
        ->and($voices->voices)->toContain('def456');
});

it('creates voice session via voice handler', function () {
    Http::fake([
        'api.elevenlabs.io/v1/convai/conversation/get-signed-url*' => Http::response([
            'signed_url' => 'wss://api.elevenlabs.io/v1/convai/conversation?signed_url=test123',
        ]),
    ]);

    $session = makeElevenLabsDriver()->createVoiceSession(new VoiceRequest(
        model: 'gpt-4o',
        instructions: 'Be helpful',
        voice: null,
        providerOptions: ['agent_id' => 'test_agent'],
    ));

    expect($session->provider)->toBe('elevenlabs');
    expect($session->connectionUrl)->toContain('signed_url=test123');
});

it('validates via provider handler', function () {
    Http::fake([
        'api.elevenlabs.io/v1/models' => Http::response([
            ['model_id' => 'eleven_multilingual_v2'],
        ]),
    ]);

    expect(makeElevenLabsDriver()->validate())->toBeTrue();
});
