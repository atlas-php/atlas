<?php

declare(strict_types=1);

use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\AudioRequest;
use Atlasphp\Atlas\Requests\EmbedRequest;
use Atlasphp\Atlas\Requests\ImageRequest;
use Atlasphp\Atlas\Requests\ModerateRequest;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Requests\VideoRequest;

function createTestDriver(): Driver
{
    $config = new ProviderConfig(apiKey: 'test', baseUrl: 'https://api.test.com');
    $http = Mockery::mock(HttpClient::class);

    return new class($config, $http) extends Driver
    {
        public function capabilities(): ProviderCapabilities
        {
            return new ProviderCapabilities;
        }

        public function name(): string
        {
            return 'test';
        }
    };
}

it('throws UnsupportedFeatureException for text', function () {
    createTestDriver()->text(new TextRequest('model', null, null, [], [], null, null, null, [], [], []));
})->throws(UnsupportedFeatureException::class, 'text');

it('throws UnsupportedFeatureException for stream', function () {
    createTestDriver()->stream(new TextRequest('model', null, null, [], [], null, null, null, [], [], []));
})->throws(UnsupportedFeatureException::class, 'text');

it('throws UnsupportedFeatureException for structured', function () {
    createTestDriver()->structured(new TextRequest('model', null, null, [], [], null, null, null, [], [], []));
})->throws(UnsupportedFeatureException::class, 'text');

it('throws UnsupportedFeatureException for image', function () {
    createTestDriver()->image(new ImageRequest('model', null, [], null, null, null));
})->throws(UnsupportedFeatureException::class, 'image');

it('throws UnsupportedFeatureException for imageToText', function () {
    createTestDriver()->imageToText(new ImageRequest('model', null, [], null, null, null));
})->throws(UnsupportedFeatureException::class, 'image');

it('throws UnsupportedFeatureException for audio', function () {
    createTestDriver()->audio(new AudioRequest('model', null, [], null, null, null, null, null, null));
})->throws(UnsupportedFeatureException::class, 'audio');

it('throws UnsupportedFeatureException for audioToText', function () {
    createTestDriver()->audioToText(new AudioRequest('model', null, [], null, null, null, null, null, null));
})->throws(UnsupportedFeatureException::class, 'audio');

it('throws UnsupportedFeatureException for video', function () {
    createTestDriver()->video(new VideoRequest('model', null, [], null, null, null));
})->throws(UnsupportedFeatureException::class, 'video');

it('throws UnsupportedFeatureException for videoToText', function () {
    createTestDriver()->videoToText(new VideoRequest('model', null, [], null, null, null));
})->throws(UnsupportedFeatureException::class, 'video');

it('throws UnsupportedFeatureException for embed', function () {
    createTestDriver()->embed(new EmbedRequest('model', 'text'));
})->throws(UnsupportedFeatureException::class, 'embed');

it('throws UnsupportedFeatureException for moderate', function () {
    createTestDriver()->moderate(new ModerateRequest('model', 'text'));
})->throws(UnsupportedFeatureException::class, 'moderate');

it('throws UnsupportedFeatureException for models', function () {
    createTestDriver()->models();
})->throws(UnsupportedFeatureException::class, 'models');

it('throws UnsupportedFeatureException for voices', function () {
    createTestDriver()->voices();
})->throws(UnsupportedFeatureException::class, 'voices');

it('throws UnsupportedFeatureException for validate on base driver', function () {
    createTestDriver()->validate();
})->throws(UnsupportedFeatureException::class, 'validate');
