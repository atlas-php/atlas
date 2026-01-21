<?php

declare(strict_types=1);

use Atlasphp\Atlas\Tools\Support\ToolContext;

test('it creates with default empty metadata', function () {
    $context = new ToolContext;

    expect($context->metadata)->toBe([]);
});

test('it creates with provided metadata', function () {
    $metadata = ['key' => 'value'];
    $context = new ToolContext($metadata);

    expect($context->metadata)->toBe($metadata);
});

test('it gets metadata value with default', function () {
    $context = new ToolContext(['key' => 'value']);

    expect($context->getMeta('key'))->toBe('value');
    expect($context->getMeta('missing', 'default'))->toBe('default');
});

test('it reports hasMeta correctly', function () {
    $context = new ToolContext(['key' => 'value']);

    expect($context->hasMeta('key'))->toBeTrue();
    expect($context->hasMeta('missing'))->toBeFalse();
});

test('it creates new instance with metadata', function () {
    $context = new ToolContext;
    $metadata = ['key' => 'value'];

    $newContext = $context->withMetadata($metadata);

    expect($newContext)->not->toBe($context);
    expect($newContext->metadata)->toBe($metadata);
    expect($context->metadata)->toBe([]);
});

test('it creates new instance with merged metadata', function () {
    $context = new ToolContext(['a' => 1]);

    $newContext = $context->mergeMetadata(['b' => 2]);

    expect($newContext)->not->toBe($context);
    expect($newContext->metadata)->toBe(['a' => 1, 'b' => 2]);
});
