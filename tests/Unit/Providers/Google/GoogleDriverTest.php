<?php

declare(strict_types=1);

use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Providers\Google\GoogleDriver;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\AudioRequest;
use Atlasphp\Atlas\Requests\ModerateRequest;
use Atlasphp\Atlas\Requests\RerankRequest;
use Atlasphp\Atlas\Requests\VideoRequest;
use Illuminate\Support\Facades\Http;

function makeGoogleDriver(array $capabilityOverrides = []): GoogleDriver
{
    return new GoogleDriver(
        config: ProviderConfig::fromArray([
            'api_key' => 'test-key',
            'url' => 'https://generativelanguage.googleapis.com',
            'capability_overrides' => $capabilityOverrides,
        ]),
        http: app(HttpClient::class),
    );
}

// ─── Identity ───────────────────────────────────────────────────────────────

it('returns google as the driver name', function () {
    expect(makeGoogleDriver()->name())->toBe('google');
});

// ─── Capabilities ───────────────────────────────────────────────────────────

it('returns correct capabilities matrix', function () {
    $capabilities = makeGoogleDriver()->capabilities();

    expect($capabilities->text)->toBeTrue();
    expect($capabilities->stream)->toBeTrue();
    expect($capabilities->structured)->toBeTrue();
    expect($capabilities->image)->toBeTrue();
    expect($capabilities->imageToText)->toBeFalse();
    expect($capabilities->audio)->toBeFalse();
    expect($capabilities->audioToText)->toBeFalse();
    expect($capabilities->video)->toBeFalse();
    expect($capabilities->videoToText)->toBeFalse();
    expect($capabilities->embed)->toBeTrue();
    expect($capabilities->moderate)->toBeFalse();
    expect($capabilities->rerank)->toBeFalse();
    expect($capabilities->vision)->toBeTrue();
    expect($capabilities->toolCalling)->toBeTrue();
    expect($capabilities->providerTools)->toBeTrue();
    expect($capabilities->models)->toBeTrue();
    expect($capabilities->voices)->toBeFalse();
});

it('applies capability overrides', function () {
    $driver = new GoogleDriver(
        config: ProviderConfig::fromArray([
            'api_key' => 'test-key',
            'url' => 'https://generativelanguage.googleapis.com',
            'capabilities' => ['text' => false, 'embed' => false],
        ]),
        http: app(HttpClient::class),
    );

    $capabilities = $driver->capabilities();

    expect($capabilities->text)->toBeFalse();
    expect($capabilities->embed)->toBeFalse();
    expect($capabilities->image)->toBeTrue();
});

// ─── Unsupported modalities throw ───────────────────────────────────────────

it('throws UnsupportedFeatureException for audio', function () {
    makeGoogleDriver()->audio(new AudioRequest('model', null, [], null, null, null, null, null, null));
})->throws(UnsupportedFeatureException::class, 'audio');

it('throws UnsupportedFeatureException for audioToText', function () {
    makeGoogleDriver()->audioToText(new AudioRequest('model', null, [], null, null, null, null, null, null));
})->throws(UnsupportedFeatureException::class, 'audio');

it('throws UnsupportedFeatureException for video', function () {
    makeGoogleDriver()->video(new VideoRequest('model', null, [], null, null, null));
})->throws(UnsupportedFeatureException::class, 'video');

it('throws UnsupportedFeatureException for videoToText', function () {
    makeGoogleDriver()->videoToText(new VideoRequest('model', null, [], null, null, null));
})->throws(UnsupportedFeatureException::class, 'video');

it('throws UnsupportedFeatureException for moderate', function () {
    makeGoogleDriver()->moderate(new ModerateRequest('test'));
})->throws(UnsupportedFeatureException::class, 'moderate');

it('throws UnsupportedFeatureException for rerank', function () {
    makeGoogleDriver()->rerank(new RerankRequest('model', 'query', ['doc']));
})->throws(UnsupportedFeatureException::class, 'rerank');

// ─── Provider handler ───────────────────────────────────────────────────────

it('lists models via provider handler', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'models' => [
                ['name' => 'models/gemini-2.0-flash', 'displayName' => 'Gemini 2.0 Flash'],
                ['name' => 'models/gemini-2.5-pro', 'displayName' => 'Gemini 2.5 Pro'],
            ],
        ]),
    ]);

    $models = makeGoogleDriver()->models();

    // Google handler strips the "models/" prefix
    expect($models->models)->toContain('gemini-2.0-flash');
    expect($models->models)->toContain('gemini-2.5-pro');
});
