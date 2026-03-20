<?php

declare(strict_types=1);

use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\OpenAi\OpenAiDriver;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\ImageRequest;
use Atlasphp\Atlas\Requests\RerankRequest;
use Atlasphp\Atlas\Requests\VideoRequest;
use Illuminate\Support\Facades\Http;

function makeOpenAiDriver(): OpenAiDriver
{
    return new OpenAiDriver(
        config: ProviderConfig::fromArray(['api_key' => 'test', 'url' => 'https://api.openai.com/v1']),
        http: app(HttpClient::class),
    );
}

it('returns openai as name', function () {
    expect(makeOpenAiDriver()->name())->toBe('openai');
});

it('reports correct capabilities', function () {
    $cap = makeOpenAiDriver()->capabilities();

    expect($cap->supports('text'))->toBeTrue();
    expect($cap->supports('stream'))->toBeTrue();
    expect($cap->supports('structured'))->toBeTrue();
    expect($cap->supports('image'))->toBeTrue();
    expect($cap->supports('imageToText'))->toBeFalse();
    expect($cap->supports('audio'))->toBeTrue();
    expect($cap->supports('audioToText'))->toBeTrue();
    expect($cap->supports('video'))->toBeTrue();
    expect($cap->supports('videoToText'))->toBeFalse();
    expect($cap->supports('embed'))->toBeTrue();
    expect($cap->supports('moderate'))->toBeTrue();
    expect($cap->supports('vision'))->toBeTrue();
    expect($cap->supports('toolCalling'))->toBeTrue();
    expect($cap->supports('providerTools'))->toBeTrue();
    expect($cap->supports('models'))->toBeTrue();
    expect($cap->supports('voices'))->toBeTrue();
});

it('lists models via provider handler', function () {
    Http::fake([
        'api.openai.com/v1/models' => Http::response([
            'data' => [
                ['id' => 'gpt-4o', 'object' => 'model'],
                ['id' => 'gpt-4o-mini', 'object' => 'model'],
            ],
        ]),
    ]);

    $models = makeOpenAiDriver()->models();

    expect($models->models)->toContain('gpt-4o');
    expect($models->models)->toContain('gpt-4o-mini');
});

it('lists voices via provider handler', function () {
    $voices = makeOpenAiDriver()->voices();

    expect($voices->voices)->toContain('alloy');
    expect($voices->voices)->toContain('nova');
    expect($voices->voices)->toContain('shimmer');
});

it('validates via provider handler', function () {
    Http::fake([
        'api.openai.com/v1/models' => Http::response([
            'data' => [['id' => 'gpt-4o']],
        ]),
    ]);

    expect(makeOpenAiDriver()->validate())->toBeTrue();
});

// ─── Unsupported modalities ─────────────────────────────────────────────────

it('throws UnsupportedFeatureException for imageToText', function () {
    makeOpenAiDriver()->imageToText(new ImageRequest('model', null, [], null, null, null));
})->throws(UnsupportedFeatureException::class);

it('throws UnsupportedFeatureException for videoToText', function () {
    makeOpenAiDriver()->videoToText(new VideoRequest('model', null, [], null, null, null));
})->throws(UnsupportedFeatureException::class);

it('throws UnsupportedFeatureException for rerank', function () {
    makeOpenAiDriver()->rerank(new RerankRequest('model', 'query', ['doc']));
})->throws(UnsupportedFeatureException::class, 'rerank');
