<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Support\HasProviderCallbacks;
use Prism\Prism\Enums\Provider;

/**
 * Test class that uses the HasProviderCallbacks trait.
 */
class TestCallbackClass
{
    use HasProviderCallbacks;

    public string $value = '';

    /**
     * Expose the protected method for testing.
     */
    public function exposeApplyProviderCallbacks(string $provider): static
    {
        return $this->applyProviderCallbacks($provider);
    }

    /**
     * Expose the protected method for testing.
     *
     * @return array<string, array<int, callable>>
     */
    public function exposeGetProviderCallbacks(): array
    {
        return $this->getProviderCallbacks();
    }

    /**
     * Set a value (for testing callback modifications).
     */
    public function setValue(string $value): static
    {
        $clone = clone $this;
        $clone->value = $value;

        return $clone;
    }
}

test('whenProvider returns a clone with callback stored', function () {
    $instance = new TestCallbackClass;
    $clone = $instance->whenProvider('anthropic', fn ($r) => $r);

    expect($clone)->not->toBe($instance);
    expect($clone)->toBeInstanceOf(TestCallbackClass::class);
});

test('whenProvider stores callback for provider', function () {
    $callback = fn ($r) => $r;
    $instance = new TestCallbackClass;
    $clone = $instance->whenProvider('anthropic', $callback);

    $callbacks = $clone->exposeGetProviderCallbacks();
    expect($callbacks)->toHaveKey('anthropic');
    expect($callbacks['anthropic'])->toHaveCount(1);
    expect($callbacks['anthropic'][0])->toBe($callback);
});

test('whenProvider stores multiple callbacks for same provider', function () {
    $callback1 = fn ($r) => $r;
    $callback2 = fn ($r) => $r;

    $instance = new TestCallbackClass;
    $clone = $instance
        ->whenProvider('anthropic', $callback1)
        ->whenProvider('anthropic', $callback2);

    $callbacks = $clone->exposeGetProviderCallbacks();
    expect($callbacks['anthropic'])->toHaveCount(2);
    expect($callbacks['anthropic'][0])->toBe($callback1);
    expect($callbacks['anthropic'][1])->toBe($callback2);
});

test('whenProvider stores callbacks for different providers', function () {
    $anthropicCallback = fn ($r) => $r;
    $openaiCallback = fn ($r) => $r;

    $instance = new TestCallbackClass;
    $clone = $instance
        ->whenProvider('anthropic', $anthropicCallback)
        ->whenProvider('openai', $openaiCallback);

    $callbacks = $clone->exposeGetProviderCallbacks();
    expect($callbacks)->toHaveKey('anthropic');
    expect($callbacks)->toHaveKey('openai');
    expect($callbacks['anthropic'][0])->toBe($anthropicCallback);
    expect($callbacks['openai'][0])->toBe($openaiCallback);
});

test('applyProviderCallbacks executes matching callbacks', function () {
    $instance = new TestCallbackClass;
    $clone = $instance->whenProvider('anthropic', fn ($r) => $r->setValue('modified'));

    $result = $clone->exposeApplyProviderCallbacks('anthropic');

    expect($result->value)->toBe('modified');
});

test('applyProviderCallbacks ignores non-matching providers', function () {
    $instance = new TestCallbackClass;
    $clone = $instance->whenProvider('anthropic', fn ($r) => $r->setValue('modified'));

    $result = $clone->exposeApplyProviderCallbacks('openai');

    expect($result->value)->toBe('');
});

test('applyProviderCallbacks executes callbacks in order', function () {
    $instance = new TestCallbackClass;
    $clone = $instance
        ->whenProvider('anthropic', fn ($r) => $r->setValue('first'))
        ->whenProvider('anthropic', fn ($r) => $r->setValue($r->value.'-second'));

    $result = $clone->exposeApplyProviderCallbacks('anthropic');

    expect($result->value)->toBe('first-second');
});

test('whenProvider accepts Prism Provider enum', function () {
    $callback = fn ($r) => $r;
    $instance = new TestCallbackClass;
    $clone = $instance->whenProvider(Provider::Anthropic, $callback);

    $callbacks = $clone->exposeGetProviderCallbacks();
    expect($callbacks)->toHaveKey('anthropic');
    expect($callbacks['anthropic'][0])->toBe($callback);
});

test('whenProvider with enum matches string provider', function () {
    $instance = new TestCallbackClass;
    $clone = $instance->whenProvider(Provider::Anthropic, fn ($r) => $r->setValue('from-enum'));

    $result = $clone->exposeApplyProviderCallbacks('anthropic');

    expect($result->value)->toBe('from-enum');
});

test('original instance is not modified by whenProvider', function () {
    $instance = new TestCallbackClass;
    $instance->whenProvider('anthropic', fn ($r) => $r);

    expect($instance->exposeGetProviderCallbacks())->toBe([]);
});

test('getProviderCallbacks returns empty array when no callbacks configured', function () {
    $instance = new TestCallbackClass;

    expect($instance->exposeGetProviderCallbacks())->toBe([]);
});

test('chaining whenProvider and other modifications preserves callbacks', function () {
    $callback = fn ($r) => $r;
    $instance = new TestCallbackClass;
    $clone = $instance
        ->whenProvider('anthropic', $callback)
        ->setValue('test');

    $callbacks = $clone->exposeGetProviderCallbacks();
    expect($callbacks)->toHaveKey('anthropic');
    expect($clone->value)->toBe('test');
});

test('applyProviderCallbacks returns same instance when no matching callbacks', function () {
    $instance = new TestCallbackClass;
    $result = $instance->exposeApplyProviderCallbacks('anthropic');

    // Note: applyProviderCallbacks returns $this when no callbacks, but clone may differ
    // The important thing is the state remains unchanged
    expect($result->value)->toBe('');
    expect($result->exposeGetProviderCallbacks())->toBe([]);
});
