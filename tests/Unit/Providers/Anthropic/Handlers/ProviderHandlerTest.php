<?php

declare(strict_types=1);

use Atlasphp\Atlas\Cache\AtlasCache;
use Atlasphp\Atlas\Providers\Anthropic\Handlers\Provider;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Illuminate\Support\Facades\Http;

function makeAnthropicProviderHandler(): Provider
{
    return new Provider(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.anthropic.com/v1']),
        http: app(HttpClient::class),
        cache: app(AtlasCache::class),
    );
}

it('lists models from data array', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'data' => [
                ['id' => 'claude-sonnet-4-5-20250514', 'type' => 'model'],
                ['id' => 'claude-3-5-haiku-20241022', 'type' => 'model'],
            ],
        ]),
    ]);

    $handler = makeAnthropicProviderHandler();
    $models = $handler->models();

    expect($models->models)->toContain('claude-sonnet-4-5-20250514');
    expect($models->models)->toContain('claude-3-5-haiku-20241022');
});

it('returns models sorted alphabetically', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'data' => [
                ['id' => 'claude-sonnet-4-5-20250514'],
                ['id' => 'claude-3-5-haiku-20241022'],
                ['id' => 'claude-opus-4-20250514'],
            ],
        ]),
    ]);

    $handler = makeAnthropicProviderHandler();
    $models = $handler->models();

    expect($models->models)->toBe(['claude-3-5-haiku-20241022', 'claude-opus-4-20250514', 'claude-sonnet-4-5-20250514']);
});

it('returns empty VoiceList', function () {
    $handler = makeAnthropicProviderHandler();
    $voices = $handler->voices();

    expect($voices->voices)->toBe([]);
});

it('validate returns true when models succeeds', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'data' => [
                ['id' => 'claude-sonnet-4-5-20250514'],
            ],
        ]),
    ]);

    $handler = makeAnthropicProviderHandler();

    expect($handler->validate())->toBeTrue();
});
