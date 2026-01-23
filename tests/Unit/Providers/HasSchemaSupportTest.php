<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Support\HasSchemaSupport;
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Schema\SchemaBuilder;
use Atlasphp\Atlas\Schema\SchemaProperty;
use Prism\Prism\Contracts\Schema as SchemaContract;
use Prism\Prism\Schema\ObjectSchema;

/**
 * Test class that uses the HasSchemaSupport trait.
 */
class TestSchemaClass
{
    use HasSchemaSupport;

    /**
     * Expose the protected method for testing.
     */
    public function exposeGetSchema(): ?SchemaContract
    {
        return $this->getSchema();
    }
}

test('withSchema returns a clone with schema', function () {
    $schema = Mockery::mock(SchemaContract::class);
    $instance = new TestSchemaClass;
    $clone = $instance->withSchema($schema);

    expect($clone)->not->toBe($instance);
    expect($clone)->toBeInstanceOf(TestSchemaClass::class);
});

test('withSchema stores schema', function () {
    $schema = Mockery::mock(SchemaContract::class);
    $instance = new TestSchemaClass;
    $clone = $instance->withSchema($schema);

    expect($clone->exposeGetSchema())->toBe($schema);
});

test('getSchema returns null when no schema configured', function () {
    $instance = new TestSchemaClass;

    expect($instance->exposeGetSchema())->toBeNull();
});

test('original instance is not modified by withSchema', function () {
    $schema = Mockery::mock(SchemaContract::class);
    $instance = new TestSchemaClass;
    $instance->withSchema($schema);

    expect($instance->exposeGetSchema())->toBeNull();
});

test('chained withSchema calls replace schema', function () {
    $schema1 = Mockery::mock(SchemaContract::class);
    $schema2 = Mockery::mock(SchemaContract::class);

    $instance = new TestSchemaClass;
    $clone1 = $instance->withSchema($schema1);
    $clone2 = $clone1->withSchema($schema2);

    expect($clone1->exposeGetSchema())->toBe($schema1);
    expect($clone2->exposeGetSchema())->toBe($schema2);
});

// ===========================================
// AUTO-BUILD TESTS
// ===========================================

test('withSchema auto-builds SchemaBuilder directly', function () {
    // Schema::object() returns a SchemaBuilder (not SchemaProperty)
    $builder = Schema::object('empty', 'Empty schema');

    expect($builder)->toBeInstanceOf(SchemaBuilder::class);

    $instance = new TestSchemaClass;
    $clone = $instance->withSchema($builder);

    $result = $clone->exposeGetSchema();

    expect($result)->toBeInstanceOf(ObjectSchema::class);
    expect($result->name)->toBe('empty');
    expect($result->description)->toBe('Empty schema');
    expect($result->properties)->toHaveCount(0);
});

test('withSchema auto-builds SchemaProperty', function () {
    // SchemaProperty is returned from builder methods
    $property = Schema::object('person', 'Person info')
        ->string('name', 'Full name')
        ->number('age', 'Age in years');

    expect($property)->toBeInstanceOf(SchemaProperty::class);

    $instance = new TestSchemaClass;
    $clone = $instance->withSchema($property);

    $result = $clone->exposeGetSchema();

    expect($result)->toBeInstanceOf(ObjectSchema::class);
    expect($result->name)->toBe('person');
    expect($result->properties)->toHaveCount(2);
});

test('withSchema auto-builds SchemaBuilder with optional fields', function () {
    $builder = Schema::object('contact', 'Contact info')
        ->string('name', 'Full name')
        ->string('email', 'Email')->optional()
        ->string('phone', 'Phone')->nullable();

    $instance = new TestSchemaClass;
    $clone = $instance->withSchema($builder);

    $result = $clone->exposeGetSchema();

    expect($result)->toBeInstanceOf(ObjectSchema::class);
    expect($result->name)->toBe('contact');
    expect($result->properties)->toHaveCount(3);
    // Only 'name' should be required
    expect($result->requiredFields)->toBe(['name']);
});

test('withSchema auto-builds SchemaBuilder with nested objects', function () {
    $builder = Schema::object('order', 'Order details')
        ->string('id', 'Order ID')
        ->object('customer', 'Customer info', fn (SchemaBuilder $s) => $s
            ->string('name', 'Name')
            ->string('email', 'Email')
        );

    $instance = new TestSchemaClass;
    $clone = $instance->withSchema($builder);

    $result = $clone->exposeGetSchema();

    expect($result)->toBeInstanceOf(ObjectSchema::class);
    expect($result->name)->toBe('order');
    expect($result->properties)->toHaveCount(2);
});

test('withSchema accepts pre-built Schema directly', function () {
    // Build explicitly
    $preBuiltSchema = Schema::object('test', 'Test')
        ->string('field', 'A field')
        ->build();

    expect($preBuiltSchema)->toBeInstanceOf(ObjectSchema::class);

    $instance = new TestSchemaClass;
    $clone = $instance->withSchema($preBuiltSchema);

    expect($clone->exposeGetSchema())->toBe($preBuiltSchema);
});

afterEach(function () {
    Mockery::close();
});
