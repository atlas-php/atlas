<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Support\HasProviderSupport;

/**
 * Test class that uses the HasProviderSupport trait.
 */
class TestProviderClass
{
    use HasProviderSupport;

    /**
     * Expose the protected method for testing.
     */
    public function exposeGetProviderOverride(): ?string
    {
        return $this->getProviderOverride();
    }

    /**
     * Expose the protected method for testing.
     */
    public function exposeGetModelOverride(): ?string
    {
        return $this->getModelOverride();
    }

    /**
     * Expose the protected method for testing.
     *
     * @return array<string, mixed>
     */
    public function exposeGetProviderOptions(): array
    {
        return $this->getProviderOptions();
    }
}

test('withProvider returns a clone with provider', function () {
    $instance = new TestProviderClass;
    $clone = $instance->withProvider('openai');

    expect($clone)->not->toBe($instance);
    expect($clone)->toBeInstanceOf(TestProviderClass::class);
});

test('withProvider stores provider', function () {
    $instance = new TestProviderClass;
    $clone = $instance->withProvider('anthropic');

    expect($clone->exposeGetProviderOverride())->toBe('anthropic');
});

test('withProvider stores provider and model', function () {
    $instance = new TestProviderClass;
    $clone = $instance->withProvider('openai', 'gpt-4');

    expect($clone->exposeGetProviderOverride())->toBe('openai');
    expect($clone->exposeGetModelOverride())->toBe('gpt-4');
});

test('getProviderOverride returns null when no provider configured', function () {
    $instance = new TestProviderClass;

    expect($instance->exposeGetProviderOverride())->toBeNull();
});

test('original instance is not modified by withProvider', function () {
    $instance = new TestProviderClass;
    $instance->withProvider('openai');

    expect($instance->exposeGetProviderOverride())->toBeNull();
});

test('withModel returns a clone with model', function () {
    $instance = new TestProviderClass;
    $clone = $instance->withModel('gpt-4');

    expect($clone)->not->toBe($instance);
    expect($clone)->toBeInstanceOf(TestProviderClass::class);
});

test('withModel stores model', function () {
    $instance = new TestProviderClass;
    $clone = $instance->withModel('claude-3-opus');

    expect($clone->exposeGetModelOverride())->toBe('claude-3-opus');
});

test('getModelOverride returns null when no model configured', function () {
    $instance = new TestProviderClass;

    expect($instance->exposeGetModelOverride())->toBeNull();
});

test('original instance is not modified by withModel', function () {
    $instance = new TestProviderClass;
    $instance->withModel('gpt-4');

    expect($instance->exposeGetModelOverride())->toBeNull();
});

test('chaining withProvider and withModel preserves both', function () {
    $instance = new TestProviderClass;
    $clone = $instance->withProvider('openai')->withModel('gpt-4');

    expect($clone->exposeGetProviderOverride())->toBe('openai');
    expect($clone->exposeGetModelOverride())->toBe('gpt-4');
});

test('chained withProvider calls replace provider', function () {
    $instance = new TestProviderClass;
    $clone1 = $instance->withProvider('openai');
    $clone2 = $clone1->withProvider('anthropic');

    expect($clone1->exposeGetProviderOverride())->toBe('openai');
    expect($clone2->exposeGetProviderOverride())->toBe('anthropic');
});

test('chained withModel calls replace model', function () {
    $instance = new TestProviderClass;
    $clone1 = $instance->withModel('gpt-4');
    $clone2 = $clone1->withModel('gpt-3.5-turbo');

    expect($clone1->exposeGetModelOverride())->toBe('gpt-4');
    expect($clone2->exposeGetModelOverride())->toBe('gpt-3.5-turbo');
});

test('withProviderOptions returns a clone with options', function () {
    $instance = new TestProviderClass;
    $clone = $instance->withProviderOptions(['style' => 'vivid']);

    expect($clone)->not->toBe($instance);
    expect($clone)->toBeInstanceOf(TestProviderClass::class);
});

test('withProviderOptions stores options', function () {
    $instance = new TestProviderClass;
    $clone = $instance->withProviderOptions(['style' => 'vivid']);

    expect($clone->exposeGetProviderOptions())->toBe(['style' => 'vivid']);
});

test('withProviderOptions merges options', function () {
    $instance = new TestProviderClass;
    $clone1 = $instance->withProviderOptions(['style' => 'vivid']);
    $clone2 = $clone1->withProviderOptions(['format' => 'json']);

    expect($clone2->exposeGetProviderOptions())->toBe([
        'style' => 'vivid',
        'format' => 'json',
    ]);
});

test('getProviderOptions returns empty array when no options configured', function () {
    $instance = new TestProviderClass;

    expect($instance->exposeGetProviderOptions())->toBe([]);
});

test('original instance is not modified by withProviderOptions', function () {
    $instance = new TestProviderClass;
    $instance->withProviderOptions(['style' => 'vivid']);

    expect($instance->exposeGetProviderOptions())->toBe([]);
});

test('chaining all methods preserves all configuration', function () {
    $instance = new TestProviderClass;
    $clone = $instance
        ->withProvider('openai', 'dall-e-3')
        ->withProviderOptions(['style' => 'vivid']);

    expect($clone->exposeGetProviderOverride())->toBe('openai');
    expect($clone->exposeGetModelOverride())->toBe('dall-e-3');
    expect($clone->exposeGetProviderOptions())->toBe(['style' => 'vivid']);
});

test('withProvider with model only sets model when provided', function () {
    $instance = new TestProviderClass;
    $clone = $instance->withProvider('anthropic');

    expect($clone->exposeGetProviderOverride())->toBe('anthropic');
    expect($clone->exposeGetModelOverride())->toBeNull();
});
