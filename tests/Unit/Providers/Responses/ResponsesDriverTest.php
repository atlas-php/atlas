<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\Responses\ResponsesDriver;

function makeResponsesDriver(array $capabilityOverrides = []): ResponsesDriver
{
    return new ResponsesDriver(
        config: new ProviderConfig(
            apiKey: 'test-key',
            baseUrl: 'http://localhost:11434/v1',
            capabilityOverrides: $capabilityOverrides,
        ),
        http: app(HttpClient::class),
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
