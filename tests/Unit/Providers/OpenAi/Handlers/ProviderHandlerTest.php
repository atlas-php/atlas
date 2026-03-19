<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\OpenAi\Handlers\Provider;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Illuminate\Support\Facades\Http;

function makeProviderHandler(): Provider
{
    return new Provider(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.openai.com/v1']),
        http: app(HttpClient::class),
    );
}

it('lists models from /v1/models', function () {
    Http::fake([
        'api.openai.com/v1/models' => Http::response([
            'data' => [
                ['id' => 'gpt-4o', 'object' => 'model'],
                ['id' => 'gpt-4o-mini', 'object' => 'model'],
                ['id' => 'dall-e-3', 'object' => 'model'],
            ],
        ]),
    ]);

    $handler = makeProviderHandler();
    $models = $handler->models();

    expect($models->models)->toContain('gpt-4o');
    expect($models->models)->toContain('gpt-4o-mini');
    expect($models->models)->toContain('dall-e-3');
});

it('returns models sorted alphabetically', function () {
    Http::fake([
        'api.openai.com/v1/models' => Http::response([
            'data' => [
                ['id' => 'zebra-model', 'object' => 'model'],
                ['id' => 'alpha-model', 'object' => 'model'],
                ['id' => 'middle-model', 'object' => 'model'],
            ],
        ]),
    ]);

    $handler = makeProviderHandler();
    $models = $handler->models();

    expect($models->models)->toBe(['alpha-model', 'middle-model', 'zebra-model']);
});

it('returns empty model list when data key is missing', function () {
    Http::fake([
        'api.openai.com/v1/models' => Http::response(['object' => 'list']),
    ]);

    $handler = makeProviderHandler();
    $models = $handler->models();

    expect($models->models)->toBe([]);
});

it('returns hardcoded voice list', function () {
    $handler = makeProviderHandler();
    $voices = $handler->voices();

    expect($voices->voices)->toContain('alloy');
    expect($voices->voices)->toContain('nova');
    expect($voices->voices)->toContain('shimmer');
    expect($voices->voices)->toContain('echo');
    expect($voices->voices)->toHaveCount(10);
});

it('validate returns true when models succeeds', function () {
    Http::fake([
        'api.openai.com/v1/models' => Http::response([
            'data' => [['id' => 'gpt-4o', 'object' => 'model']],
        ]),
    ]);

    $handler = makeProviderHandler();

    expect($handler->validate())->toBeTrue();
});
