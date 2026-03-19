<?php

declare(strict_types=1);

use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\Xai\Handlers\Audio;
use Atlasphp\Atlas\Requests\AudioRequest;
use Atlasphp\Atlas\Responses\AudioResponse;
use Illuminate\Support\Facades\Http;

function makeXaiAudioHandler(): Audio
{
    return new Audio(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.x.ai/v1']),
        http: app(HttpClient::class),
    );
}

function makeXaiAudioRequest(array $overrides = []): AudioRequest
{
    return new AudioRequest(
        model: $overrides['model'] ?? 'tts-1',
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

it('posts to /v1/tts with text, voice_id, and language', function () {
    Http::fake([
        'api.x.ai/v1/tts' => Http::response('fake-audio-binary-data'),
    ]);

    $handler = makeXaiAudioHandler();
    $response = $handler->audio(makeXaiAudioRequest());

    expect($response)->toBeInstanceOf(AudioResponse::class);
    expect($response->data)->toBe(base64_encode('fake-audio-binary-data'));

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.x.ai/v1/tts'
            && $request['text'] === 'Hello world'
            && $request['voice_id'] === 'eve'
            && $request['language'] === 'en';
    });
});

it('defaults voice to eve', function () {
    Http::fake([
        'api.x.ai/v1/tts' => Http::response('audio'),
    ]);

    $handler = makeXaiAudioHandler();
    $handler->audio(makeXaiAudioRequest());

    Http::assertSent(function ($request) {
        return $request['voice_id'] === 'eve';
    });
});

it('defaults language to en', function () {
    Http::fake([
        'api.x.ai/v1/tts' => Http::response('audio'),
    ]);

    $handler = makeXaiAudioHandler();
    $handler->audio(makeXaiAudioRequest());

    Http::assertSent(function ($request) {
        return $request['language'] === 'en';
    });
});

it('uses custom voice and language', function () {
    Http::fake([
        'api.x.ai/v1/tts' => Http::response('audio'),
    ]);

    $handler = makeXaiAudioHandler();
    $handler->audio(makeXaiAudioRequest(['voice' => 'leo', 'language' => 'fr']));

    Http::assertSent(function ($request) {
        return $request['voice_id'] === 'leo'
            && $request['language'] === 'fr';
    });
});

it('base64 encodes the response', function () {
    $binaryData = random_bytes(100);

    Http::fake([
        'api.x.ai/v1/tts' => Http::response($binaryData),
    ]);

    $handler = makeXaiAudioHandler();
    $response = $handler->audio(makeXaiAudioRequest());

    expect($response->data)->toBe(base64_encode($binaryData));
    expect($response->format)->toBe('mp3');
});

it('uses custom format', function () {
    Http::fake([
        'api.x.ai/v1/tts' => Http::response('audio'),
    ]);

    $handler = makeXaiAudioHandler();
    $response = $handler->audio(makeXaiAudioRequest(['format' => 'wav']));

    expect($response->format)->toBe('wav');
});

it('passes provider options through', function () {
    Http::fake([
        'api.x.ai/v1/tts' => Http::response('audio'),
    ]);

    $handler = makeXaiAudioHandler();
    $handler->audio(makeXaiAudioRequest(['providerOptions' => ['output_format' => 'wav']]));

    Http::assertSent(function ($request) {
        return $request['output_format'] === 'wav';
    });
});

it('audioToText throws UnsupportedFeatureException', function () {
    $handler = makeXaiAudioHandler();

    $handler->audioToText(makeXaiAudioRequest());
})->throws(UnsupportedFeatureException::class);
