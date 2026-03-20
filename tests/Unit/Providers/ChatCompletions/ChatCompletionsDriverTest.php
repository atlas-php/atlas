<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\ChatCompletions\ChatCompletionsDriver;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;

function makeCcDriver(array $capabilityOverrides = []): ChatCompletionsDriver
{
    return new ChatCompletionsDriver(
        config: new ProviderConfig(
            apiKey: 'test-key',
            baseUrl: 'http://localhost:11434/v1',
            capabilityOverrides: $capabilityOverrides,
        ),
        http: app(HttpClient::class),
    );
}

it('returns chat_completions as name', function () {
    expect(makeCcDriver()->name())->toBe('chat_completions');
});

it('reports correct default capabilities', function () {
    $cap = makeCcDriver()->capabilities();

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
    $cap = makeCcDriver(['structured' => false, 'vision' => false])->capabilities();

    expect($cap->supports('text'))->toBeTrue();
    expect($cap->supports('structured'))->toBeFalse();
    expect($cap->supports('vision'))->toBeFalse();
});
