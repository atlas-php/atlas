<?php

declare(strict_types=1);

use Atlasphp\Atlas\Foundation\Contracts\ExtensionResolverContract;
use Atlasphp\Atlas\Foundation\Exceptions\AtlasException;
use Atlasphp\Atlas\Foundation\Services\AbstractExtensionRegistry;

beforeEach(function () {
    $this->registry = new ConcreteExtensionRegistry;
});

test('it can register a resolver', function () {
    $resolver = new TestResolver('test-key', 'test-value');

    $this->registry->register($resolver);

    expect($this->registry->supports('test-key'))->toBeTrue();
});

test('it throws on duplicate registration', function () {
    $resolver1 = new TestResolver('test-key', 'value1');
    $resolver2 = new TestResolver('test-key', 'value2');

    $this->registry->register($resolver1);

    expect(fn () => $this->registry->register($resolver2))
        ->toThrow(AtlasException::class, "A extension resolver with key 'test-key' has already been registered.");
});

test('it can get resolved value by key', function () {
    $resolver = new TestResolver('test-key', 'resolved-value');
    $this->registry->register($resolver);

    $result = $this->registry->get('test-key');

    expect($result)->toBe('resolved-value');
});

test('it throws when getting unknown key', function () {
    expect(fn () => $this->registry->get('unknown-key'))
        ->toThrow(AtlasException::class, "No extension resolver found with key 'unknown-key'.");
});

test('it reports supports correctly', function () {
    expect($this->registry->supports('test-key'))->toBeFalse();

    $this->registry->register(new TestResolver('test-key', 'value'));

    expect($this->registry->supports('test-key'))->toBeTrue();
});

test('it returns registered keys', function () {
    $this->registry->register(new TestResolver('key-a', 'value-a'));
    $this->registry->register(new TestResolver('key-b', 'value-b'));

    $registered = $this->registry->registered();

    expect($registered)->toContain('key-a');
    expect($registered)->toContain('key-b');
});

test('it reports hasResolvers correctly', function () {
    expect($this->registry->hasResolvers())->toBeFalse();

    $this->registry->register(new TestResolver('test-key', 'value'));

    expect($this->registry->hasResolvers())->toBeTrue();
});

test('it counts registered resolvers', function () {
    expect($this->registry->count())->toBe(0);

    $this->registry->register(new TestResolver('key-a', 'value'));
    expect($this->registry->count())->toBe(1);

    $this->registry->register(new TestResolver('key-b', 'value'));
    expect($this->registry->count())->toBe(2);
});

test('it supports method chaining', function () {
    $result = $this->registry->register(new TestResolver('test-key', 'value'));

    expect($result)->toBeInstanceOf(AbstractExtensionRegistry::class);
});

// Test Classes

class ConcreteExtensionRegistry extends AbstractExtensionRegistry {}

class TestResolver implements ExtensionResolverContract
{
    public function __construct(
        private string $key,
        private mixed $value,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function resolve(): mixed
    {
        return $this->value;
    }

    public function supports(string $key): bool
    {
        return $this->key === $key;
    }
}
