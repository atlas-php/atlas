<?php

declare(strict_types=1);

use Atlasphp\Atlas\Cache\AtlasCache;
use Atlasphp\Atlas\Providers\ChatCompletions\Handlers\Provider;
use Atlasphp\Atlas\Providers\Handlers\AudioHandler;
use Atlasphp\Atlas\Providers\Handlers\EmbedHandler;
use Atlasphp\Atlas\Providers\Handlers\ImageHandler;
use Atlasphp\Atlas\Providers\Handlers\ModerateHandler;
use Atlasphp\Atlas\Providers\Handlers\ProviderHandler;
use Atlasphp\Atlas\Providers\Handlers\TextHandler;
use Atlasphp\Atlas\Providers\Handlers\VideoHandler;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\OpenAi\Handlers\Audio;
use Atlasphp\Atlas\Providers\OpenAi\Handlers\Embed;
use Atlasphp\Atlas\Providers\OpenAi\Handlers\Image;
use Atlasphp\Atlas\Providers\OpenAi\Handlers\Moderate;
use Atlasphp\Atlas\Providers\OpenAi\Handlers\Text;
use Atlasphp\Atlas\Providers\OpenAi\Handlers\Video;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\ResponsesDriver;

function makeResponsesDriver(array $capabilityOverrides = []): ResponsesDriver
{
    return new ResponsesDriver(
        config: new ProviderConfig(
            apiKey: 'test-key',
            baseUrl: 'http://localhost:11434/v1',
            capabilityOverrides: $capabilityOverrides,
        ),
        http: app(HttpClient::class),
        cache: app(AtlasCache::class),
    );
}

it('returns responses as name', function () {
    expect(makeResponsesDriver()->name())->toBe('responses');
});

it('reports correct default capabilities', function () {
    $cap = makeResponsesDriver()->capabilities();

    expect($cap->supports('text'))->toBeTrue();
    expect($cap->supports('stream'))->toBeTrue();
    expect($cap->supports('structured'))->toBeTrue();
    expect($cap->supports('image'))->toBeTrue();
    expect($cap->supports('audio'))->toBeTrue();
    expect($cap->supports('audioToText'))->toBeTrue();
    expect($cap->supports('video'))->toBeTrue();
    expect($cap->supports('embed'))->toBeTrue();
    expect($cap->supports('moderate'))->toBeTrue();
    expect($cap->supports('vision'))->toBeTrue();
    expect($cap->supports('toolCalling'))->toBeTrue();
    expect($cap->supports('providerTools'))->toBeFalse();
});

it('applies capability overrides from config', function () {
    $cap = makeResponsesDriver(['embed' => false])->capabilities();

    expect($cap->supports('text'))->toBeTrue();
    expect($cap->supports('embed'))->toBeFalse();
});

it('supports models capability', function () {
    $cap = makeResponsesDriver()->capabilities();

    expect($cap->supports('models'))->toBeTrue();
});

it('can disable multiple capabilities via overrides', function () {
    $cap = makeResponsesDriver([
        'image' => false,
        'audio' => false,
        'video' => false,
    ])->capabilities();

    expect($cap->supports('text'))->toBeTrue();
    expect($cap->supports('image'))->toBeFalse();
    expect($cap->supports('audio'))->toBeFalse();
    expect($cap->supports('video'))->toBeFalse();
});

// ─── Handler instantiation ──────────────────────────────────────────
// Each handler method must return the correct type and be backed by OpenAI handlers.

it('returns a ProviderHandler from providerHandler', function () {
    $driver = makeResponsesDriver();
    $method = new ReflectionMethod($driver, 'providerHandler');

    $handler = $method->invoke($driver);

    expect($handler)->toBeInstanceOf(ProviderHandler::class)
        ->and($handler)->toBeInstanceOf(Provider::class);
});

it('returns a TextHandler from textHandler', function () {
    $driver = makeResponsesDriver();
    $method = new ReflectionMethod($driver, 'textHandler');

    $handler = $method->invoke($driver);

    expect($handler)->toBeInstanceOf(TextHandler::class)
        ->and($handler)->toBeInstanceOf(Text::class);
});

it('returns an ImageHandler from imageHandler', function () {
    $driver = makeResponsesDriver();
    $method = new ReflectionMethod($driver, 'imageHandler');

    $handler = $method->invoke($driver);

    expect($handler)->toBeInstanceOf(ImageHandler::class)
        ->and($handler)->toBeInstanceOf(Image::class);
});

it('returns an AudioHandler from audioHandler', function () {
    $driver = makeResponsesDriver();
    $method = new ReflectionMethod($driver, 'audioHandler');

    $handler = $method->invoke($driver);

    expect($handler)->toBeInstanceOf(AudioHandler::class)
        ->and($handler)->toBeInstanceOf(Audio::class);
});

it('returns a VideoHandler from videoHandler', function () {
    $driver = makeResponsesDriver();
    $method = new ReflectionMethod($driver, 'videoHandler');

    $handler = $method->invoke($driver);

    expect($handler)->toBeInstanceOf(VideoHandler::class)
        ->and($handler)->toBeInstanceOf(Video::class);
});

it('returns an EmbedHandler from embedHandler', function () {
    $driver = makeResponsesDriver();
    $method = new ReflectionMethod($driver, 'embedHandler');

    $handler = $method->invoke($driver);

    expect($handler)->toBeInstanceOf(EmbedHandler::class)
        ->and($handler)->toBeInstanceOf(Embed::class);
});

it('returns a ModerateHandler from moderateHandler', function () {
    $driver = makeResponsesDriver();
    $method = new ReflectionMethod($driver, 'moderateHandler');

    $handler = $method->invoke($driver);

    expect($handler)->toBeInstanceOf(ModerateHandler::class)
        ->and($handler)->toBeInstanceOf(Moderate::class);
});
