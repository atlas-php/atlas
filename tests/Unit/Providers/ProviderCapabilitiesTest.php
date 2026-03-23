<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\ProviderCapabilities;

it('returns true for supported features', function () {
    $caps = new ProviderCapabilities(text: true, stream: true, models: true, voices: true, realtime: true);

    expect($caps->supports('text'))->toBeTrue();
    expect($caps->supports('stream'))->toBeTrue();
    expect($caps->supports('models'))->toBeTrue();
    expect($caps->supports('voices'))->toBeTrue();
    expect($caps->supports('realtime'))->toBeTrue();
});

it('returns false for unsupported features', function () {
    $caps = new ProviderCapabilities;

    expect($caps->supports('text'))->toBeFalse();
    expect($caps->supports('image'))->toBeFalse();
});

it('returns false for nonexistent features', function () {
    $caps = new ProviderCapabilities(text: true);

    expect($caps->supports('nonexistent'))->toBeFalse();
});

it('withOverrides merges config overrides with defaults', function () {
    $base = new ProviderCapabilities(text: true, stream: true, structured: true, vision: true);

    $overridden = ProviderCapabilities::withOverrides($base, [
        'structured' => false,
        'vision' => false,
    ]);

    expect($overridden->supports('text'))->toBeTrue();
    expect($overridden->supports('stream'))->toBeTrue();
    expect($overridden->supports('structured'))->toBeFalse();
    expect($overridden->supports('vision'))->toBeFalse();
});

it('withOverrides returns same instance when overrides are empty', function () {
    $base = new ProviderCapabilities(text: true);

    $result = ProviderCapabilities::withOverrides($base, []);

    expect($result)->toBe($base);
});
