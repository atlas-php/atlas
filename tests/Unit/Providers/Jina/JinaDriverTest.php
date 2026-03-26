<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasCache;
use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\Jina\JinaDriver;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\AudioRequest;
use Atlasphp\Atlas\Requests\EmbedRequest;
use Atlasphp\Atlas\Requests\ImageRequest;
use Atlasphp\Atlas\Requests\ModerateRequest;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Requests\VideoRequest;

function makeJinaDriver(): JinaDriver
{
    return new JinaDriver(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.jina.ai']),
        http: app(HttpClient::class),
        cache: app(AtlasCache::class),
    );
}

// ─── Identity ───────────────────────────────────────────────────────────────

it('returns jina as name', function () {
    expect(makeJinaDriver()->name())->toBe('jina');
});

// ─── Capabilities ───────────────────────────────────────────────────────────

it('reports correct capabilities', function () {
    $cap = makeJinaDriver()->capabilities();

    expect($cap->supports('rerank'))->toBeTrue()
        ->and($cap->supports('text'))->toBeFalse()
        ->and($cap->supports('stream'))->toBeFalse()
        ->and($cap->supports('structured'))->toBeFalse()
        ->and($cap->supports('image'))->toBeFalse()
        ->and($cap->supports('imageToText'))->toBeFalse()
        ->and($cap->supports('audio'))->toBeFalse()
        ->and($cap->supports('audioToText'))->toBeFalse()
        ->and($cap->supports('video'))->toBeFalse()
        ->and($cap->supports('videoToText'))->toBeFalse()
        ->and($cap->supports('embed'))->toBeFalse()
        ->and($cap->supports('moderate'))->toBeFalse()
        ->and($cap->supports('vision'))->toBeFalse()
        ->and($cap->supports('toolCalling'))->toBeFalse()
        ->and($cap->supports('providerTools'))->toBeFalse()
        ->and($cap->supports('models'))->toBeFalse()
        ->and($cap->supports('voices'))->toBeFalse();
});

// ─── Unsupported modalities throw ───────────────────────────────────────────

it('throws UnsupportedFeatureException for text', function () {
    makeJinaDriver()->text(new TextRequest('model', null, null, [], [], null, null, null, [], [], []));
})->throws(UnsupportedFeatureException::class);

it('throws UnsupportedFeatureException for image', function () {
    makeJinaDriver()->image(new ImageRequest('model', null, [], null, null, null));
})->throws(UnsupportedFeatureException::class);

it('throws UnsupportedFeatureException for audio', function () {
    makeJinaDriver()->audio(new AudioRequest('model', null, [], null, null, null, null, null, null));
})->throws(UnsupportedFeatureException::class);

it('throws UnsupportedFeatureException for video', function () {
    makeJinaDriver()->video(new VideoRequest('model', null, [], null, null, null));
})->throws(UnsupportedFeatureException::class);

it('throws UnsupportedFeatureException for embed', function () {
    makeJinaDriver()->embed(new EmbedRequest('model', 'text'));
})->throws(UnsupportedFeatureException::class);

it('throws UnsupportedFeatureException for moderate', function () {
    makeJinaDriver()->moderate(new ModerateRequest('test'));
})->throws(UnsupportedFeatureException::class);
