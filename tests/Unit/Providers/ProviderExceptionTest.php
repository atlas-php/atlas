<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Exceptions\ProviderException;

test('it creates exception for unknown provider', function () {
    $exception = ProviderException::unknownProvider('invalid-provider');

    expect($exception)->toBeInstanceOf(ProviderException::class);
    expect($exception->getMessage())->toBe("Unknown provider: 'invalid-provider'.");
});

test('it creates exception for missing configuration', function () {
    $exception = ProviderException::missingConfiguration('api_key', 'openai');

    expect($exception)->toBeInstanceOf(ProviderException::class);
    expect($exception->getMessage())->toBe("Missing configuration 'api_key' for provider 'openai'.");
});

test('it creates exception for api error', function () {
    $exception = ProviderException::apiError('anthropic', 'Rate limit exceeded', 429);

    expect($exception)->toBeInstanceOf(ProviderException::class);
    expect($exception->getMessage())->toBe("API error from 'anthropic': Rate limit exceeded");
    expect($exception->getCode())->toBe(429);
});

test('it creates api error exception with default code', function () {
    $exception = ProviderException::apiError('openai', 'Internal server error');

    expect($exception)->toBeInstanceOf(ProviderException::class);
    expect($exception->getCode())->toBe(0);
});
