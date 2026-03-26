<?php

declare(strict_types=1);

use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Providers\ElevenLabs\Handlers\Sfx;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\AudioRequest;
use Illuminate\Support\Facades\Http;

function makeSfxHandler(): Sfx
{
    return new Sfx(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.elevenlabs.io/v1']),
        http: app(HttpClient::class),
    );
}

function makeSfxRequest(array $overrides = []): AudioRequest
{
    return new AudioRequest(
        model: $overrides['model'] ?? 'eleven_text_to_sound_v2',
        instructions: array_key_exists('instructions', $overrides) ? $overrides['instructions'] : 'Thunder rumbling',
        media: [],
        voice: null,
        speed: null,
        language: null,
        duration: $overrides['duration'] ?? null,
        format: $overrides['format'] ?? null,
        voiceClone: null,
        providerOptions: $overrides['providerOptions'] ?? [],
    );
}

it('posts to /sound-generation with text', function () {
    Http::fake([
        'api.elevenlabs.io/*' => Http::response('sfx-audio'),
    ]);

    $response = makeSfxHandler()->audio(makeSfxRequest());

    expect($response->data)->toBe(base64_encode('sfx-audio'))
        ->and($response->format)->toBe('mp3');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/sound-generation')
            && $request['text'] === 'Thunder rumbling'
            && $request['model_id'] === 'eleven_text_to_sound_v2';
    });
});

it('sends duration_seconds as float', function () {
    Http::fake([
        'api.elevenlabs.io/*' => Http::response('audio'),
    ]);

    makeSfxHandler()->audio(makeSfxRequest(['duration' => 10]));

    Http::assertSent(fn ($r) => $r['duration_seconds'] === 10.0);
});

it('sends loop option', function () {
    Http::fake([
        'api.elevenlabs.io/*' => Http::response('audio'),
    ]);

    makeSfxHandler()->audio(makeSfxRequest(['providerOptions' => ['loop' => true]]));

    Http::assertSent(fn ($r) => $r['loop'] === true);
});

it('sends prompt_influence', function () {
    Http::fake([
        'api.elevenlabs.io/*' => Http::response('audio'),
    ]);

    makeSfxHandler()->audio(makeSfxRequest(['providerOptions' => ['prompt_influence' => 0.7]]));

    Http::assertSent(fn ($r) => $r['prompt_influence'] === 0.7);
});

it('sends output_format as query parameter', function () {
    Http::fake([
        'api.elevenlabs.io/*' => Http::response('audio'),
    ]);

    makeSfxHandler()->audio(makeSfxRequest(['format' => 'pcm_16000']));

    Http::assertSent(fn ($r) => str_contains($r->url(), 'output_format=pcm_16000'));
});

it('uses xi-api-key header', function () {
    Http::fake([
        'api.elevenlabs.io/*' => Http::response('audio'),
    ]);

    makeSfxHandler()->audio(makeSfxRequest());

    Http::assertSent(fn ($r) => $r->header('xi-api-key')[0] === 'test-key');
});

it('throws when instructions is null', function () {
    makeSfxHandler()->audio(makeSfxRequest(['instructions' => null]));
})->throws(InvalidArgumentException::class, 'Sound effect generation requires');
