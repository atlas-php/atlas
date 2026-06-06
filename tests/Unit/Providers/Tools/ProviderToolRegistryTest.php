<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Tools\ProviderToolRegistry;

it('exposes the full provider support map', function () {
    expect(ProviderToolRegistry::all())->toBe([
        'openai' => ['web_search', 'file_search', 'code_interpreter'],
        'anthropic' => ['web_search', 'web_fetch'],
        'google' => ['google_search', 'code_execution'],
        'xai' => ['web_search', 'x_search'],
    ]);
});

it('returns supported tool types per provider', function () {
    expect(ProviderToolRegistry::forProvider('anthropic'))->toBe(['web_search', 'web_fetch']);
    expect(ProviderToolRegistry::forProvider('openai'))->toContain('file_search');
});

it('returns an empty list for an unknown provider', function () {
    expect(ProviderToolRegistry::forProvider('nope'))->toBe([]);
});

it('answers whether a provider supports a tool type', function () {
    expect(ProviderToolRegistry::supports('anthropic', 'web_search'))->toBeTrue();
    expect(ProviderToolRegistry::supports('anthropic', 'web_fetch'))->toBeTrue();
    // web_fetch is Anthropic-only — OpenAI has no such tool.
    expect(ProviderToolRegistry::supports('openai', 'web_fetch'))->toBeFalse();
    expect(ProviderToolRegistry::supports('xai', 'x_search'))->toBeTrue();
    expect(ProviderToolRegistry::supports('google', 'google_search'))->toBeTrue();
});
