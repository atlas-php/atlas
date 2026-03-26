<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasCache;
use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Providers\Cohere\CohereDriver;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\AudioRequest;
use Atlasphp\Atlas\Requests\EmbedRequest;
use Atlasphp\Atlas\Requests\ImageRequest;
use Atlasphp\Atlas\Requests\ModerateRequest;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Requests\VideoRequest;

function makeCohereDriver(): CohereDriver
{
    return new CohereDriver(
        config: ProviderConfig::fromArray(['api_key' => 'test', 'url' => 'https://api.cohere.com']),
        http: app(HttpClient::class),
        cache: app(AtlasCache::class),
    );
}

it('returns cohere as name', function () {
    expect(makeCohereDriver()->name())->toBe('cohere');
});

it('reports rerank as the only capability', function () {
    $cap = makeCohereDriver()->capabilities();

    expect($cap->supports('rerank'))->toBeTrue();
    expect($cap->supports('text'))->toBeFalse();
    expect($cap->supports('stream'))->toBeFalse();
    expect($cap->supports('image'))->toBeFalse();
    expect($cap->supports('audio'))->toBeFalse();
    expect($cap->supports('video'))->toBeFalse();
    expect($cap->supports('embed'))->toBeFalse();
    expect($cap->supports('moderate'))->toBeFalse();
    expect($cap->supports('voice'))->toBeFalse();
});

it('throws UnsupportedFeatureException for text', function () {
    makeCohereDriver()->text(new TextRequest('model', null, null, [], [], null, null, null, [], [], []));
})->throws(UnsupportedFeatureException::class);

it('throws UnsupportedFeatureException for image', function () {
    makeCohereDriver()->image(new ImageRequest('model', null, [], null, null, null));
})->throws(UnsupportedFeatureException::class);

it('throws UnsupportedFeatureException for audio', function () {
    makeCohereDriver()->audio(new AudioRequest('model', null, [], null, null, null, null, null, null));
})->throws(UnsupportedFeatureException::class);

it('throws UnsupportedFeatureException for video', function () {
    makeCohereDriver()->video(new VideoRequest('model', null, [], null, null, null));
})->throws(UnsupportedFeatureException::class);

it('throws UnsupportedFeatureException for embed', function () {
    makeCohereDriver()->embed(new EmbedRequest('model', 'text'));
})->throws(UnsupportedFeatureException::class);

it('throws UnsupportedFeatureException for moderate', function () {
    makeCohereDriver()->moderate(new ModerateRequest('model', 'text'));
})->throws(UnsupportedFeatureException::class);
