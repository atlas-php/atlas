<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Exceptions\ProviderNotRegisteredException;
use Atlasphp\Atlas\Providers\ProviderRegistry;

beforeEach(function () {
    $this->registry = new ProviderRegistry;
});

it('registers and resolves a provider', function () {
    $this->registry->register('openai', fn () => 'openai-driver');

    expect($this->registry->resolve('openai'))->toBe('openai-driver');
});

it('caches resolved instances', function () {
    $callCount = 0;

    $this->registry->register('openai', function () use (&$callCount) {
        $callCount++;

        return new stdClass;
    });

    $first = $this->registry->resolve('openai');
    $second = $this->registry->resolve('openai');

    expect($first)->toBe($second);
    expect($callCount)->toBe(1);
});

it('throws ProviderNotRegisteredException for unknown key', function () {
    $this->registry->resolve('unknown');
})->throws(ProviderNotRegisteredException::class, 'No provider registered for key [unknown].');

it('returns true for registered keys', function () {
    $this->registry->register('openai', fn () => 'driver');

    expect($this->registry->has('openai'))->toBeTrue();
});

it('returns false for unregistered keys', function () {
    expect($this->registry->has('openai'))->toBeFalse();
});

it('returns all registered keys', function () {
    $this->registry->register('openai', fn () => 'a');
    $this->registry->register('anthropic', fn () => 'b');

    expect($this->registry->available())->toBe(['openai', 'anthropic']);
});

it('clears cache when re-registering a key', function () {
    $this->registry->register('openai', fn () => 'old');
    $this->registry->resolve('openai');

    $this->registry->register('openai', fn () => 'new');

    expect($this->registry->resolve('openai'))->toBe('new');
});
