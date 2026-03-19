<?php

declare(strict_types=1);

it('has openai as the default provider', function () {
    expect(config('atlas.default.provider'))->toBe('openai');
});

it('has gpt-4o as the default model', function () {
    expect(config('atlas.default.model'))->toBe('gpt-4o');
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
