<?php

declare(strict_types=1);

use Atlasphp\Atlas\Cache\AtlasCache;
use Atlasphp\Atlas\Providers\ElevenLabs\Handlers\Provider;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Illuminate\Support\Facades\Http;

function makeElevenLabsProviderHandler(): Provider
{
    return new Provider(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.elevenlabs.io/v1']),
        http: app(HttpClient::class),
        cache: app(AtlasCache::class),
    );
}

it('lists models from flat array with model_id key', function () {
    Http::fake([
        'api.elevenlabs.io/v1/models' => Http::response([
            ['model_id' => 'eleven_multilingual_v2', 'name' => 'Eleven Multilingual v2'],
            ['model_id' => 'eleven_flash_v2_5', 'name' => 'Eleven Flash v2.5'],
            ['model_id' => 'scribe_v2', 'name' => 'Scribe v2'],
        ]),
    ]);

    $models = makeElevenLabsProviderHandler()->models();

    expect($models->models)->toContain('eleven_multilingual_v2')
        ->and($models->models)->toContain('eleven_flash_v2_5')
        ->and($models->models)->toContain('scribe_v2')
        ->and($models->models)->toHaveCount(3);
});

it('returns models sorted alphabetically', function () {
    Http::fake([
        'api.elevenlabs.io/v1/models' => Http::response([
            ['model_id' => 'scribe_v2'],
            ['model_id' => 'eleven_flash_v2_5'],
            ['model_id' => 'eleven_multilingual_v2'],
        ]),
    ]);

    $models = makeElevenLabsProviderHandler()->models();

    expect($models->models)->toBe(['eleven_flash_v2_5', 'eleven_multilingual_v2', 'scribe_v2']);
});

it('lists voices from voices array with voice_id key', function () {
    Http::fake([
        'api.elevenlabs.io/v1/voices' => Http::response([
            'voices' => [
                ['voice_id' => 'abc123', 'name' => 'Rachel'],
                ['voice_id' => 'def456', 'name' => 'Adam'],
            ],
        ]),
    ]);

    $voices = makeElevenLabsProviderHandler()->voices();

    expect($voices->voices)->toContain('abc123')
        ->and($voices->voices)->toContain('def456')
        ->and($voices->voices)->toHaveCount(2);
});

it('returns voices sorted alphabetically', function () {
    Http::fake([
        'api.elevenlabs.io/v1/voices' => Http::response([
            'voices' => [
                ['voice_id' => 'zzz'],
                ['voice_id' => 'aaa'],
            ],
        ]),
    ]);

    $voices = makeElevenLabsProviderHandler()->voices();

    expect($voices->voices)->toBe(['aaa', 'zzz']);
});

it('uses xi-api-key header not Bearer', function () {
    Http::fake([
        'api.elevenlabs.io/*' => Http::response([]),
    ]);

    makeElevenLabsProviderHandler()->models();

    Http::assertSent(function ($request) {
        return $request->header('xi-api-key')[0] === 'test-key'
            && empty($request->header('Authorization'));
    });
});

it('cache key prefix includes api key hash', function () {
    $handler = makeElevenLabsProviderHandler();

    $reflection = new ReflectionMethod($handler, 'cacheKeyPrefix');
    $prefix = $reflection->invoke($handler);

    expect($prefix)->toStartWith('elevenlabs:')
        ->and(strlen($prefix))->toBe(strlen('elevenlabs:') + 8);
});

it('validate returns true', function () {
    Http::fake([
        'api.elevenlabs.io/v1/models' => Http::response([
            ['model_id' => 'eleven_multilingual_v2'],
        ]),
    ]);

    expect(makeElevenLabsProviderHandler()->validate())->toBeTrue();
});

it('handles empty voices array gracefully', function () {
    Http::fake([
        'api.elevenlabs.io/v1/voices' => Http::response(['voices' => []]),
    ]);

    $voices = makeElevenLabsProviderHandler()->voices();

    expect($voices->voices)->toBe([]);
});
