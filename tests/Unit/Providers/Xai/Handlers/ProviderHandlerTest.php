<?php

declare(strict_types=1);

use Atlasphp\Atlas\Cache\AtlasCache;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\Xai\Handlers\Provider;
use Illuminate\Support\Facades\Http;

function makeXaiProviderHandler(): Provider
{
    return new Provider(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.x.ai/v1']),
        http: app(HttpClient::class),
        cache: app(AtlasCache::class),
    );
}

it('fetches voices from /v1/tts/voices', function () {
    Http::fake([
        'api.x.ai/v1/tts/voices' => Http::response([
            'voices' => [
                ['voice_id' => 'ara', 'name' => 'Ara', 'language' => 'multilingual'],
                ['voice_id' => 'eve', 'name' => 'Eve', 'language' => 'multilingual'],
                ['voice_id' => 'leo', 'name' => 'Leo', 'language' => 'multilingual'],
                ['voice_id' => 'rex', 'name' => 'Rex', 'language' => 'multilingual'],
                ['voice_id' => 'sal', 'name' => 'Sal', 'language' => 'multilingual'],
                ['voice_id' => 'una', 'name' => 'Una', 'language' => 'multilingual'],
            ],
        ]),
    ]);

    $handler = makeXaiProviderHandler();
    $voices = $handler->voices();

    expect($voices->voices)->toHaveCount(6);
    expect($voices->voices)->toContain('ara');
    expect($voices->voices)->toContain('eve');
    expect($voices->voices)->toContain('una');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.x.ai/v1/tts/voices';
    });
});

it('returns voices sorted alphabetically', function () {
    Http::fake([
        'api.x.ai/v1/tts/voices' => Http::response([
            'voices' => [
                ['voice_id' => 'sal', 'name' => 'Sal', 'language' => 'multilingual'],
                ['voice_id' => 'ara', 'name' => 'Ara', 'language' => 'multilingual'],
                ['voice_id' => 'leo', 'name' => 'Leo', 'language' => 'multilingual'],
            ],
        ]),
    ]);

    $handler = makeXaiProviderHandler();
    $voices = $handler->voices();

    expect($voices->voices)->toBe(['ara', 'leo', 'sal']);
});

it('returns empty voice list when voices key is missing', function () {
    Http::fake([
        'api.x.ai/v1/tts/voices' => Http::response(['status' => 'ok']),
    ]);

    $handler = makeXaiProviderHandler();
    $voices = $handler->voices();

    expect($voices->voices)->toBe([]);
});

it('fetches models from /v1/models', function () {
    Http::fake([
        'api.x.ai/v1/models' => Http::response([
            'data' => [
                ['id' => 'grok-3', 'object' => 'model'],
                ['id' => 'grok-3-mini', 'object' => 'model'],
            ],
        ]),
    ]);

    $handler = makeXaiProviderHandler();
    $models = $handler->models();

    expect($models->models)->toContain('grok-3');
    expect($models->models)->toContain('grok-3-mini');
});

it('validate returns true when models succeeds', function () {
    Http::fake([
        'api.x.ai/v1/models' => Http::response([
            'data' => [['id' => 'grok-3', 'object' => 'model']],
        ]),
    ]);

    $handler = makeXaiProviderHandler();

    expect($handler->validate())->toBeTrue();
});

it('validate returns false when models endpoint throws', function () {
    Http::fake([
        'api.x.ai/v1/models' => Http::response('Unauthorized', 401),
    ]);

    $handler = makeXaiProviderHandler();

    expect($handler->validate())->toBeFalse();
});
