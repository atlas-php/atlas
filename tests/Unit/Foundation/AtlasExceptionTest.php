<?php

declare(strict_types=1);

use Atlasphp\Atlas\Foundation\Exceptions\AtlasException;

test('it creates exception for duplicate registration', function () {
    $exception = AtlasException::duplicateRegistration('agent', 'support-agent');

    expect($exception)->toBeInstanceOf(AtlasException::class);
    expect($exception->getMessage())->toBe("A agent with key 'support-agent' has already been registered.");
});

test('it creates exception for not found', function () {
    $exception = AtlasException::notFound('tool', 'calculator');

    expect($exception)->toBeInstanceOf(AtlasException::class);
    expect($exception->getMessage())->toBe("No tool found with key 'calculator'.");
});

test('it creates exception for invalid configuration', function () {
    $exception = AtlasException::invalidConfiguration('Missing API key for provider');

    expect($exception)->toBeInstanceOf(AtlasException::class);
    expect($exception->getMessage())->toBe('Invalid configuration: Missing API key for provider');
});
