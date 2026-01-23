<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Support\HasVariablesSupport;

/**
 * Test class that uses the HasVariablesSupport trait.
 */
class TestVariablesClass
{
    use HasVariablesSupport;

    /**
     * Expose the protected method for testing.
     *
     * @return array<string, mixed>
     */
    public function exposeGetVariables(): array
    {
        return $this->getVariables();
    }
}

test('withVariables returns a clone with variables', function () {
    $instance = new TestVariablesClass;
    $clone = $instance->withVariables(['key' => 'value']);

    expect($clone)->not->toBe($instance);
    expect($clone)->toBeInstanceOf(TestVariablesClass::class);
});

test('withVariables stores variables', function () {
    $instance = new TestVariablesClass;
    $variables = ['user_name' => 'John', 'role' => 'admin'];
    $clone = $instance->withVariables($variables);

    expect($clone->exposeGetVariables())->toBe($variables);
});

test('getVariables returns empty array when no variables configured', function () {
    $instance = new TestVariablesClass;

    expect($instance->exposeGetVariables())->toBe([]);
});

test('original instance is not modified by withVariables', function () {
    $instance = new TestVariablesClass;
    $instance->withVariables(['key' => 'value']);

    expect($instance->exposeGetVariables())->toBe([]);
});

test('chained withVariables calls replace variables', function () {
    $instance = new TestVariablesClass;
    $clone1 = $instance->withVariables(['key1' => 'value1']);
    $clone2 = $clone1->withVariables(['key2' => 'value2']);

    expect($clone1->exposeGetVariables())->toBe(['key1' => 'value1']);
    expect($clone2->exposeGetVariables())->toBe(['key2' => 'value2']);
});

test('withVariables handles nested arrays', function () {
    $instance = new TestVariablesClass;
    $variables = [
        'user' => [
            'name' => 'John',
            'preferences' => ['theme' => 'dark'],
        ],
    ];
    $clone = $instance->withVariables($variables);

    expect($clone->exposeGetVariables())->toBe($variables);
});

test('withVariables handles mixed value types', function () {
    $instance = new TestVariablesClass;
    $variables = [
        'string' => 'value',
        'number' => 42,
        'bool' => true,
        'null' => null,
        'array' => [1, 2, 3],
    ];
    $clone = $instance->withVariables($variables);

    expect($clone->exposeGetVariables())->toBe($variables);
});
