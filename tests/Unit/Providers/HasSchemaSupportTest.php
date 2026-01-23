<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Support\HasSchemaSupport;
use Prism\Prism\Contracts\Schema;

/**
 * Test class that uses the HasSchemaSupport trait.
 */
class TestSchemaClass
{
    use HasSchemaSupport;

    /**
     * Expose the protected method for testing.
     */
    public function exposeGetSchema(): ?Schema
    {
        return $this->getSchema();
    }
}

test('withSchema returns a clone with schema', function () {
    $schema = Mockery::mock(Schema::class);
    $instance = new TestSchemaClass;
    $clone = $instance->withSchema($schema);

    expect($clone)->not->toBe($instance);
    expect($clone)->toBeInstanceOf(TestSchemaClass::class);
});

test('withSchema stores schema', function () {
    $schema = Mockery::mock(Schema::class);
    $instance = new TestSchemaClass;
    $clone = $instance->withSchema($schema);

    expect($clone->exposeGetSchema())->toBe($schema);
});

test('getSchema returns null when no schema configured', function () {
    $instance = new TestSchemaClass;

    expect($instance->exposeGetSchema())->toBeNull();
});

test('original instance is not modified by withSchema', function () {
    $schema = Mockery::mock(Schema::class);
    $instance = new TestSchemaClass;
    $instance->withSchema($schema);

    expect($instance->exposeGetSchema())->toBeNull();
});

test('chained withSchema calls replace schema', function () {
    $schema1 = Mockery::mock(Schema::class);
    $schema2 = Mockery::mock(Schema::class);

    $instance = new TestSchemaClass;
    $clone1 = $instance->withSchema($schema1);
    $clone2 = $clone1->withSchema($schema2);

    expect($clone1->exposeGetSchema())->toBe($schema1);
    expect($clone2->exposeGetSchema())->toBe($schema2);
});

afterEach(function () {
    Mockery::close();
});
