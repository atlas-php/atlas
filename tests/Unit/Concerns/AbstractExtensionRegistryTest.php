<?php

declare(strict_types=1);

use Atlasphp\Atlas\Concerns\AbstractExtensionRegistry;
use Atlasphp\Atlas\Contracts\ExtensionResolverContract;
use Atlasphp\Atlas\Exceptions\AtlasException;

beforeEach(function () {
    $this->registry = new class extends AbstractExtensionRegistry {};
});

function createResolver(string $key, mixed $value = null): ExtensionResolverContract
{
    return new class($key, $value) implements ExtensionResolverContract
    {
        public function __construct(
            private readonly string $resolverKey,
            private readonly mixed $resolvedValue,
        ) {}

        public function key(): string
        {
            return $this->resolverKey;
        }

        public function resolve(): mixed
        {
            return $this->resolvedValue;
        }

        public function supports(string $key): bool
        {
            return $this->resolverKey === $key;
        }
    };
}

// === register ===

test('register adds a resolver', function () {
    $resolver = createResolver('my-ext', 'value');

    $result = $this->registry->register($resolver);

    expect($result)->toBe($this->registry);
    expect($this->registry->supports('my-ext'))->toBeTrue();
});

test('register throws on duplicate key', function () {
    $this->registry->register(createResolver('dup'));

    $this->registry->register(createResolver('dup'));
})->throws(AtlasException::class, "A extension resolver with key 'dup' has already been registered.");

// === get ===

test('get returns resolved value', function () {
    $this->registry->register(createResolver('ext', 'resolved-value'));

    expect($this->registry->get('ext'))->toBe('resolved-value');
});

test('get throws for unknown key', function () {
    $this->registry->get('nonexistent');
})->throws(AtlasException::class, "No extension resolver found with key 'nonexistent'.");

test('get can return different types', function () {
    $this->registry->register(createResolver('string-ext', 'hello'));
    $this->registry->register(createResolver('int-ext', 42));
    $this->registry->register(createResolver('array-ext', ['a', 'b']));
    $this->registry->register(createResolver('null-ext', null));

    expect($this->registry->get('string-ext'))->toBe('hello');
    expect($this->registry->get('int-ext'))->toBe(42);
    expect($this->registry->get('array-ext'))->toBe(['a', 'b']);
    expect($this->registry->get('null-ext'))->toBeNull();
});

// === supports ===

test('supports returns true for registered key', function () {
    $this->registry->register(createResolver('present'));

    expect($this->registry->supports('present'))->toBeTrue();
});

test('supports returns false for unregistered key', function () {
    expect($this->registry->supports('absent'))->toBeFalse();
});

// === registered ===

test('registered returns all keys', function () {
    $this->registry->register(createResolver('alpha'));
    $this->registry->register(createResolver('beta'));
    $this->registry->register(createResolver('gamma'));

    expect($this->registry->registered())->toBe(['alpha', 'beta', 'gamma']);
});

test('registered returns empty array when none registered', function () {
    expect($this->registry->registered())->toBe([]);
});

// === hasResolvers ===

test('hasResolvers returns false when empty', function () {
    expect($this->registry->hasResolvers())->toBeFalse();
});

test('hasResolvers returns true when populated', function () {
    $this->registry->register(createResolver('ext'));

    expect($this->registry->hasResolvers())->toBeTrue();
});

// === count ===

test('count returns zero when empty', function () {
    expect($this->registry->count())->toBe(0);
});

test('count returns correct number', function () {
    $this->registry->register(createResolver('one'));
    $this->registry->register(createResolver('two'));
    $this->registry->register(createResolver('three'));

    expect($this->registry->count())->toBe(3);
});
