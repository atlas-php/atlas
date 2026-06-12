<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Providers\ProviderCapabilities;

it('returns true for supported features', function () {
    $caps = new ProviderCapabilities(text: true, stream: true, models: true, voices: true, voice: true);

    expect($caps->supports('text'))->toBeTrue();
    expect($caps->supports('stream'))->toBeTrue();
    expect($caps->supports('models'))->toBeTrue();
    expect($caps->supports('voices'))->toBeTrue();
    expect($caps->supports('voice'))->toBeTrue();
});

it('returns false for unsupported features', function () {
    $caps = new ProviderCapabilities;

    expect($caps->supports('text'))->toBeFalse();
    expect($caps->supports('image'))->toBeFalse();
    expect($caps->supports('caching'))->toBeFalse();
});

it('reports caching as a supported capability when declared', function () {
    $caps = new ProviderCapabilities(text: true, caching: true);

    expect($caps->supports('caching'))->toBeTrue();
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

it('withOverrides can enable previously disabled features', function () {
    $base = new ProviderCapabilities(text: true);

    $overridden = ProviderCapabilities::withOverrides($base, [
        'image' => true,
        'embed' => true,
    ]);

    expect($overridden->supports('text'))->toBeTrue();
    expect($overridden->supports('image'))->toBeTrue();
    expect($overridden->supports('embed'))->toBeTrue();
    expect($overridden->supports('stream'))->toBeFalse();
});

it('withOverrides returns same instance when overrides are empty', function () {
    $base = new ProviderCapabilities(text: true);

    $result = ProviderCapabilities::withOverrides($base, []);

    expect($result)->toBe($base);
});

it('canBatch requires both the batch flag and the modality allow-list', function () {
    $caps = new ProviderCapabilities(
        batch: true,
        batchModalities: ['text', 'embed'],
    );

    expect($caps->canBatch('text'))->toBeTrue();
    expect($caps->canBatch(Modality::Embed))->toBeTrue();
    expect($caps->canBatch('image'))->toBeFalse();
    expect($caps->canBatch(Modality::Voice))->toBeFalse();
});

it('canBatch is false when the provider lacks the batch flag entirely', function () {
    $caps = new ProviderCapabilities(text: true, batchModalities: ['text']);

    expect($caps->canBatch('text'))->toBeFalse();
});

it('supports() ignores the non-bool batchModalities property', function () {
    $caps = new ProviderCapabilities(batch: true, batchModalities: ['text']);

    expect($caps->supports('batch'))->toBeTrue();
    expect($caps->supports('batchModalities'))->toBeFalse();
});

it('withOverrides preserves batch capability fields', function () {
    $base = new ProviderCapabilities(batch: true, batchModalities: ['text']);

    $overridden = ProviderCapabilities::withOverrides($base, ['stream' => true]);

    expect($overridden->supports('batch'))->toBeTrue();
    expect($overridden->canBatch('text'))->toBeTrue();
    expect($overridden->supports('stream'))->toBeTrue();
});
