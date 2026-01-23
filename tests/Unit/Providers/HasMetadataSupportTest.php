<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Support\HasMetadataSupport;

/**
 * Test class that uses the HasMetadataSupport trait.
 */
class TestMetadataClass
{
    use HasMetadataSupport;

    /**
     * Expose the protected method for testing.
     *
     * @return array<string, mixed>
     */
    public function exposeGetMetadata(): array
    {
        return $this->getMetadata();
    }
}

test('withMetadata returns a clone with metadata', function () {
    $instance = new TestMetadataClass;
    $clone = $instance->withMetadata(['key' => 'value']);

    expect($clone)->not->toBe($instance);
    expect($clone)->toBeInstanceOf(TestMetadataClass::class);
});

test('withMetadata stores metadata', function () {
    $instance = new TestMetadataClass;
    $metadata = ['session_id' => 'abc123', 'user_id' => 456];
    $clone = $instance->withMetadata($metadata);

    expect($clone->exposeGetMetadata())->toBe($metadata);
});

test('getMetadata returns empty array when no metadata configured', function () {
    $instance = new TestMetadataClass;

    expect($instance->exposeGetMetadata())->toBe([]);
});

test('original instance is not modified by withMetadata', function () {
    $instance = new TestMetadataClass;
    $instance->withMetadata(['key' => 'value']);

    expect($instance->exposeGetMetadata())->toBe([]);
});

test('chained withMetadata calls replace metadata', function () {
    $instance = new TestMetadataClass;
    $clone1 = $instance->withMetadata(['key1' => 'value1']);
    $clone2 = $clone1->withMetadata(['key2' => 'value2']);

    expect($clone1->exposeGetMetadata())->toBe(['key1' => 'value1']);
    expect($clone2->exposeGetMetadata())->toBe(['key2' => 'value2']);
});

test('withMetadata handles nested arrays', function () {
    $instance = new TestMetadataClass;
    $metadata = [
        'context' => [
            'request_id' => 'req-123',
            'headers' => ['content-type' => 'application/json'],
        ],
    ];
    $clone = $instance->withMetadata($metadata);

    expect($clone->exposeGetMetadata())->toBe($metadata);
});

test('withMetadata handles mixed value types', function () {
    $instance = new TestMetadataClass;
    $metadata = [
        'string' => 'value',
        'number' => 42,
        'float' => 3.14,
        'bool' => true,
        'null' => null,
        'array' => [1, 2, 3],
    ];
    $clone = $instance->withMetadata($metadata);

    expect($clone->exposeGetMetadata())->toBe($metadata);
});

test('withMetadata handles empty metadata array', function () {
    $instance = new TestMetadataClass;
    $instance = $instance->withMetadata(['key' => 'value']);
    $clone = $instance->withMetadata([]);

    expect($clone->exposeGetMetadata())->toBe([]);
});

test('metadata is passed to pipelines correctly', function () {
    $instance = new TestMetadataClass;
    $metadata = ['trace_id' => 'trace-abc', 'span_id' => 'span-123'];

    $clone = $instance->withMetadata($metadata);

    expect($clone->exposeGetMetadata()['trace_id'])->toBe('trace-abc');
    expect($clone->exposeGetMetadata()['span_id'])->toBe('span-123');
});
