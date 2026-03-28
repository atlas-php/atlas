<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasCache;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Providers\Google\Handlers\Provider;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Illuminate\Support\Facades\Http;

function makeGoogleProviderHandler(): Provider
{
    return new Provider(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://generativelanguage.googleapis.com']),
        http: app(HttpClient::class),
        cache: app(AtlasCache::class),
    );
}

it('lists models with models/ prefix stripped', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'models' => [
                ['name' => 'models/gemini-2.5-flash', 'displayName' => 'Gemini 2.5 Flash'],
                ['name' => 'models/gemini-2.5-pro', 'displayName' => 'Gemini 2.5 Pro'],
            ],
        ]),
    ]);

    $handler = makeGoogleProviderHandler();
    $models = $handler->models();

    expect($models->models)->toContain('gemini-2.5-flash');
    expect($models->models)->toContain('gemini-2.5-pro');
    expect($models->models)->not->toContain('models/gemini-2.5-flash');
});

it('returns models sorted alphabetically', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'models' => [
                ['name' => 'models/zebra-model'],
                ['name' => 'models/alpha-model'],
                ['name' => 'models/middle-model'],
            ],
        ]),
    ]);

    $handler = makeGoogleProviderHandler();
    $models = $handler->models();

    expect($models->models)->toBe(['alpha-model', 'middle-model', 'zebra-model']);
});

it('returns empty VoiceList', function () {
    $handler = makeGoogleProviderHandler();
    $voices = $handler->voices();

    expect($voices->voices)->toBe([]);
});
