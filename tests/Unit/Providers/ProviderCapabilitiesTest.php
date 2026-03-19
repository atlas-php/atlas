<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\ProviderCapabilities;

it('returns true for supported features', function () {
    $caps = new ProviderCapabilities(text: true, stream: true);

    expect($caps->supports('text'))->toBeTrue();
    expect($caps->supports('stream'))->toBeTrue();
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
