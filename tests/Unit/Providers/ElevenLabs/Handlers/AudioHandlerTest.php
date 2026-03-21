<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\ElevenLabs\Handlers\Audio;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\AudioRequest;
use Atlasphp\Atlas\Responses\AudioResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Illuminate\Support\Facades\Http;

function makeElevenLabsAudioHandler(): Audio
{
    return new Audio(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.elevenlabs.io/v1']),
        http: app(HttpClient::class),
    );
}

function makeAudioReq(array $overrides = []): AudioRequest
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
    );
}

// ─── TTS ────────────────────────────────────────────────────────────────────

it('posts to /text-to-speech/{voice_id} with correct body', function () {
    Http::fake([
        'api.elevenlabs.io/*' => Http::response('audio-bytes'),
    ]);

    $handler = makeElevenLabsAudioHandler();
    $response = $handler->audio(makeAudioReq(['voice' => 'abc123']));

    expect($response)->toBeInstanceOf(AudioResponse::class)
        ->and($response->data)->toBe(base64_encode('audio-bytes'));

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/text-to-speech/abc123')
            && $request['text'] === 'Hello world'
            && $request['model_id'] === 'eleven_multilingual_v2';
    });
});

it('uses default voice Rachel when none specified', function () {
    Http::fake([
        'api.elevenlabs.io/*' => Http::response('audio'),
    ]);

    makeElevenLabsAudioHandler()->audio(makeAudioReq());

    Http::assertSent(fn ($r) => str_contains($r->url(), '/text-to-speech/21m00Tcm4TlvDq8ikWAM'));
});

it('sends voice_settings with stability and similarity_boost', function () {
    Http::fake([
        'api.elevenlabs.io/*' => Http::response('audio'),
    ]);

    makeElevenLabsAudioHandler()->audio(makeAudioReq([
        'providerOptions' => ['stability' => 0.7, 'similarity_boost' => 0.8],
    ]));

    Http::assertSent(function ($request) {
        return $request['voice_settings']['stability'] === 0.7
            && $request['voice_settings']['similarity_boost'] === 0.8;
    });
});

it('maps speed to voice_settings', function () {
    Http::fake([
        'api.elevenlabs.io/*' => Http::response('audio'),
    ]);

    makeElevenLabsAudioHandler()->audio(makeAudioReq(['speed' => 1.5]));

    Http::assertSent(fn ($r) => $r['voice_settings']['speed'] === 1.5);
});

it('sends language_code from language', function () {
    Http::fake([
        'api.elevenlabs.io/*' => Http::response('audio'),
    ]);

    makeElevenLabsAudioHandler()->audio(makeAudioReq(['language' => 'fr']));

    Http::assertSent(fn ($r) => $r['language_code'] === 'fr');
});

it('sends output_format as query parameter', function () {
    Http::fake([
        'api.elevenlabs.io/*' => Http::response('audio'),
    ]);

    makeElevenLabsAudioHandler()->audio(makeAudioReq(['format' => 'pcm_16000']));

    Http::assertSent(fn ($r) => str_contains($r->url(), 'output_format=pcm_16000'));
});

it('extracts format codec from output_format string', function () {
    Http::fake([
        'api.elevenlabs.io/*' => Http::response('audio'),
    ]);

    $response = makeElevenLabsAudioHandler()->audio(makeAudioReq(['format' => 'pcm_16000']));

    expect($response->format)->toBe('pcm');
});

it('defaults format to mp3', function () {
    Http::fake([
        'api.elevenlabs.io/*' => Http::response('audio'),
    ]);

    $response = makeElevenLabsAudioHandler()->audio(makeAudioReq());

    expect($response->format)->toBe('mp3');
});

it('uses xi-api-key header for auth', function () {
    Http::fake([
        'api.elevenlabs.io/*' => Http::response('audio'),
    ]);

    makeElevenLabsAudioHandler()->audio(makeAudioReq());

    Http::assertSent(fn ($r) => $r->header('xi-api-key')[0] === 'test-key');
});

it('passes through provider options excluding reserved keys', function () {
    Http::fake([
        'api.elevenlabs.io/*' => Http::response('audio'),
    ]);

    makeElevenLabsAudioHandler()->audio(makeAudioReq([
        'providerOptions' => ['seed' => 42, 'stability' => 0.5],
    ]));

    Http::assertSent(function ($request) {
        return $request['seed'] === 42
            && isset($request['voice_settings']['stability']);
    });
});

it('base64 encodes binary response', function () {
    $binary = random_bytes(100);

    Http::fake([
        'api.elevenlabs.io/*' => Http::response($binary),
    ]);

    $response = makeElevenLabsAudioHandler()->audio(makeAudioReq());

    expect($response->data)->toBe(base64_encode($binary));
});

// ─── STT ────────────────────────────────────────────────────────────────────

it('stt posts multipart to /speech-to-text', function () {
    Http::fake([
        'api.elevenlabs.io/v1/speech-to-text' => Http::response(['text' => 'Hello world']),
    ]);

    $tmpFile = tempnam(sys_get_temp_dir(), 'atlas_test_').'.mp3';
    file_put_contents($tmpFile, 'fake-audio-content');

    try {
        $response = makeElevenLabsAudioHandler()->audioToText(makeAudioReq([
            'model' => 'scribe_v2',
            'media' => [Atlasphp\Atlas\Input\Audio::fromPath($tmpFile)],
        ]));

        expect($response)->toBeInstanceOf(TextResponse::class)
            ->and($response->text)->toBe('Hello world');
    } finally {
        unlink($tmpFile);
    }
});

it('stt throws when no media provided', function () {
    makeElevenLabsAudioHandler()->audioToText(makeAudioReq(['media' => []]));
})->throws(InvalidArgumentException::class, 'Audio input is required');

it('stt passes language and provider options', function () {
    Http::fake([
        'api.elevenlabs.io/v1/speech-to-text' => Http::response(['text' => 'transcribed']),
    ]);

    $tmpFile = tempnam(sys_get_temp_dir(), 'atlas_test_').'.mp3';
    file_put_contents($tmpFile, 'audio');

    try {
        makeElevenLabsAudioHandler()->audioToText(makeAudioReq([
            'media' => [Atlasphp\Atlas\Input\Audio::fromPath($tmpFile)],
            'language' => 'en',
            'providerOptions' => ['diarize' => true],
        ]));

        Http::assertSent(function ($request) {
            $body = $request->body();

            return str_contains($body, 'language_code')
                && str_contains($body, 'diarize');
        });
    } finally {
        unlink($tmpFile);
    }
});
