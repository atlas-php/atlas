<?php

declare(strict_types=1);

use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Providers\Anthropic\AnthropicDriver;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\AudioRequest;
use Atlasphp\Atlas\Requests\ImageRequest;
use Atlasphp\Atlas\Requests\ModerateRequest;
use Atlasphp\Atlas\Requests\RerankRequest;
use Atlasphp\Atlas\Requests\VideoRequest;
use Illuminate\Support\Facades\Http;

function makeAnthropicDriver(array $capabilityOverrides = []): AnthropicDriver
{
    return new AnthropicDriver(
        config: ProviderConfig::fromArray([
            'api_key' => 'test-key',
            'url' => 'https://api.anthropic.com/v1',
            'capabilities' => $capabilityOverrides,
        ]),
        http: app(HttpClient::class),
    );
}

// ─── Identity ───────────────────────────────────────────────────────────────

it('returns anthropic as the driver name', function () {
    expect(makeAnthropicDriver()->name())->toBe('anthropic');
});

// ─── Capabilities ───────────────────────────────────────────────────────────

it('returns correct capabilities matrix', function () {
    $capabilities = makeAnthropicDriver()->capabilities();

    expect($capabilities->text)->toBeTrue();
    expect($capabilities->stream)->toBeTrue();
    expect($capabilities->structured)->toBeTrue();
    expect($capabilities->image)->toBeFalse();
    expect($capabilities->imageToText)->toBeFalse();
    expect($capabilities->audio)->toBeFalse();
    expect($capabilities->audioToText)->toBeFalse();
    expect($capabilities->video)->toBeFalse();
    expect($capabilities->videoToText)->toBeFalse();
    expect($capabilities->embed)->toBeFalse();
    expect($capabilities->moderate)->toBeFalse();
    expect($capabilities->rerank)->toBeFalse();
    expect($capabilities->vision)->toBeTrue();
    expect($capabilities->toolCalling)->toBeTrue();
    expect($capabilities->providerTools)->toBeFalse();
    expect($capabilities->models)->toBeTrue();
    expect($capabilities->voices)->toBeFalse();
});

it('applies capability overrides', function () {
    $driver = new AnthropicDriver(
        config: ProviderConfig::fromArray([
            'api_key' => 'test-key',
            'url' => 'https://api.anthropic.com/v1',
            'capabilities' => ['text' => false, 'vision' => false],
        ]),
        http: app(HttpClient::class),
    );

    $capabilities = $driver->capabilities();

    expect($capabilities->text)->toBeFalse();
    expect($capabilities->vision)->toBeFalse();
    expect($capabilities->stream)->toBeTrue();
});

// ─── Unsupported modalities throw ───────────────────────────────────────────

it('throws UnsupportedFeatureException for image', function () {
    makeAnthropicDriver()->image(new ImageRequest('model', null, [], null, null, null));
})->throws(UnsupportedFeatureException::class, 'image');

it('throws UnsupportedFeatureException for audio', function () {
    makeAnthropicDriver()->audio(new AudioRequest('model', null, [], null, null, null, null, null, null));
})->throws(UnsupportedFeatureException::class, 'audio');

it('throws UnsupportedFeatureException for video', function () {
    makeAnthropicDriver()->video(new VideoRequest('model', null, [], null, null, null));
})->throws(UnsupportedFeatureException::class, 'video');

it('throws UnsupportedFeatureException for moderate', function () {
    makeAnthropicDriver()->moderate(new ModerateRequest('test'));
})->throws(UnsupportedFeatureException::class, 'moderate');

it('throws UnsupportedFeatureException for rerank', function () {
    makeAnthropicDriver()->rerank(new RerankRequest('model', 'query', ['doc']));
})->throws(UnsupportedFeatureException::class, 'rerank');

// ─── Provider handler ───────────────────────────────────────────────────────

it('lists models via provider handler', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'data' => [
                ['id' => 'claude-sonnet-4-5-20250514', 'type' => 'model'],
                ['id' => 'claude-3-5-haiku-20241022', 'type' => 'model'],
            ],
        ]),
    ]);

    $models = makeAnthropicDriver()->models();

    expect($models->models)->toContain('claude-sonnet-4-5-20250514');
    expect($models->models)->toContain('claude-3-5-haiku-20241022');
});
