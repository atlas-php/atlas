<?php

declare(strict_types=1);

it('has modality defaults configured as null', function () {
    expect(config('atlas.defaults.text.provider'))->toBeNull();
    expect(config('atlas.defaults.text.model'))->toBeNull();
    expect(config('atlas.defaults.image.provider'))->toBeNull();
    expect(config('atlas.defaults.video.provider'))->toBeNull();
    expect(config('atlas.defaults.embed.provider'))->toBeNull();
    expect(config('atlas.defaults.moderate.provider'))->toBeNull();
    expect(config('atlas.defaults.speech.provider'))->toBeNull();
    expect(config('atlas.defaults.music.provider'))->toBeNull();
    expect(config('atlas.defaults.sfx.provider'))->toBeNull();
});

it('has all core providers configured', function () {
    $providers = config('atlas.providers');

    expect($providers)->toHaveKeys(['openai', 'anthropic', 'google', 'xai']);
});

it('has retry values configured', function () {
    expect(config('atlas.retry.timeout'))->toBe(60);
    expect(config('atlas.retry.rate_limit'))->toBe(3);
    expect(config('atlas.retry.errors'))->toBe(2);
});

it('has queue configured as string', function () {
    expect(config('atlas.queue'))->toBe('default');
});
