<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
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

test('it creates with null agent by default', function () {
    $context = new ToolContext;

    expect($context->agent)->toBeNull();
    expect($context->getAgent())->toBeNull();
});

test('it creates with provided agent', function () {
    $agent = Mockery::mock(AgentContract::class);
    $context = new ToolContext([], $agent);

    expect($context->agent)->toBe($agent);
    expect($context->getAgent())->toBe($agent);
});

test('it provides access to agent key via getAgent', function () {
    $agent = Mockery::mock(AgentContract::class);
    $agent->shouldReceive('key')->andReturn('support-agent');

    $context = new ToolContext([], $agent);

    expect($context->getAgent()->key())->toBe('support-agent');
});

test('it creates with both metadata and agent', function () {
    $agent = Mockery::mock(AgentContract::class);
    $metadata = ['user_id' => 123];

    $context = new ToolContext($metadata, $agent);

    expect($context->metadata)->toBe($metadata);
    expect($context->getMeta('user_id'))->toBe(123);
    expect($context->getAgent())->toBe($agent);
});

test('withMetadata preserves agent reference', function () {
    $agent = Mockery::mock(AgentContract::class);
    $context = new ToolContext(['key' => 'value'], $agent);

    $newContext = $context->withMetadata(['new' => 'data']);

    expect($newContext->metadata)->toBe(['new' => 'data']);
    expect($newContext->getAgent())->toBe($agent);
});

test('mergeMetadata preserves agent reference', function () {
    $agent = Mockery::mock(AgentContract::class);
    $context = new ToolContext(['a' => 1], $agent);

    $newContext = $context->mergeMetadata(['b' => 2]);

    expect($newContext->metadata)->toBe(['a' => 1, 'b' => 2]);
    expect($newContext->getAgent())->toBe($agent);
});

test('clearMetadata removes all metadata', function () {
    $context = new ToolContext(['a' => 1, 'b' => 2]);

    $newContext = $context->clearMetadata();

    expect($newContext)->not->toBe($context);
    expect($newContext->metadata)->toBe([]);
    expect($context->metadata)->toBe(['a' => 1, 'b' => 2]);
});

test('clearMetadata preserves agent reference', function () {
    $agent = Mockery::mock(AgentContract::class);
    $context = new ToolContext(['a' => 1], $agent);

    $newContext = $context->clearMetadata();

    expect($newContext->metadata)->toBe([]);
    expect($newContext->getAgent())->toBe($agent);
});
