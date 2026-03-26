<?php

declare(strict_types=1);

use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Providers\ElevenLabs\Handlers\Music;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\AudioRequest;
use Illuminate\Support\Facades\Http;

function makeMusicHandler(): Music
{
    return new Music(
        config: ProviderConfig::fromArray([
            'api_key' => 'test-key',
            'url' => 'https://api.elevenlabs.io/v1',
            'media_timeout' => 120,
        ]),
        http: app(HttpClient::class),
    );
}

function makeMusicRequest(array $overrides = []): AudioRequest
{
    return new AudioRequest(
        model: $overrides['model'] ?? 'music_v1',
        instructions: array_key_exists('instructions', $overrides) ? $overrides['instructions'] : 'Lo-fi hip hop piano',
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

it('posts to /music with prompt', function () {
    Http::fake([
        'api.elevenlabs.io/*' => Http::response('music-audio'),
    ]);

    $response = makeMusicHandler()->audio(makeMusicRequest());

    expect($response->data)->toBe(base64_encode('music-audio'))
        ->and($response->format)->toBe('mp3');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/music')
            && $request['prompt'] === 'Lo-fi hip hop piano';
    });
});

it('converts duration from seconds to milliseconds', function () {
    Http::fake([
        'api.elevenlabs.io/*' => Http::response('audio'),
    ]);

    makeMusicHandler()->audio(makeMusicRequest(['duration' => 60]));

    Http::assertSent(fn ($r) => $r['music_length_ms'] === 60000);
});

it('sends composition_plan instead of prompt when provided', function () {
    Http::fake([
        'api.elevenlabs.io/*' => Http::response('audio'),
    ]);

    $plan = ['style' => 'jazz', 'sections' => [['name' => 'intro']]];

    makeMusicHandler()->audio(makeMusicRequest([
        'providerOptions' => ['composition_plan' => $plan],
    ]));

    Http::assertSent(function ($request) use ($plan) {
        return $request['composition_plan'] === $plan
            && ! isset($request['prompt']);
    });
});

it('sends strict_section_timing option', function () {
    Http::fake([
        'api.elevenlabs.io/*' => Http::response('audio'),
    ]);

    makeMusicHandler()->audio(makeMusicRequest([
        'providerOptions' => [
            'composition_plan' => ['style' => 'rock'],
            'strict_section_timing' => true,
        ],
    ]));

    Http::assertSent(fn ($r) => $r['strict_section_timing'] === true);
});

it('uses xi-api-key header', function () {
    Http::fake([
        'api.elevenlabs.io/*' => Http::response('audio'),
    ]);

    makeMusicHandler()->audio(makeMusicRequest());

    Http::assertSent(fn ($r) => $r->header('xi-api-key')[0] === 'test-key');
});

it('sends output_format as query parameter', function () {
    Http::fake([
        'api.elevenlabs.io/*' => Http::response('audio'),
    ]);

    makeMusicHandler()->audio(makeMusicRequest(['format' => 'pcm_16000']));

    Http::assertSent(fn ($r) => str_contains($r->url(), 'output_format=pcm_16000'));
});

it('throws when instructions is null and no composition_plan', function () {
    makeMusicHandler()->audio(makeMusicRequest(['instructions' => null]));
})->throws(InvalidArgumentException::class, 'Music generation requires');

it('does not throw when instructions is null but composition_plan provided', function () {
    Http::fake([
        'api.elevenlabs.io/*' => Http::response('audio'),
    ]);

    $response = makeMusicHandler()->audio(makeMusicRequest([
        'instructions' => null,
        'providerOptions' => ['composition_plan' => ['style' => 'ambient']],
    ]));

    expect($response->data)->toBe(base64_encode('audio'));
});
