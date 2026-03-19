<?php

declare(strict_types=1);

it('has modality defaults configured as null', function () {
    expect(config('atlas.defaults.text.provider'))->toBeNull();
    expect(config('atlas.defaults.text.model'))->toBeNull();
    expect(config('atlas.defaults.image.provider'))->toBeNull();
    expect(config('atlas.defaults.tts.provider'))->toBeNull();
    expect(config('atlas.defaults.stt.provider'))->toBeNull();
    expect(config('atlas.defaults.video.provider'))->toBeNull();
    expect(config('atlas.defaults.embed.provider'))->toBeNull();
    expect(config('atlas.defaults.moderate.provider'))->toBeNull();
});

it('has all four providers configured', function () {
    $providers = config('atlas.providers');

    expect($providers)->toHaveKeys(['openai', 'anthropic', 'google', 'xai']);
});

it('has timeout values configured', function () {
    expect(config('atlas.timeout.default'))->toBe(60);
    expect(config('atlas.timeout.reasoning'))->toBe(300);
    expect(config('atlas.timeout.media'))->toBe(120);
});
