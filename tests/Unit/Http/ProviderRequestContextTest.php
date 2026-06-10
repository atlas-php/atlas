<?php

declare(strict_types=1);

use Atlasphp\Atlas\Http\ProviderRequestContext;

it('defaults every field to null', function () {
    $context = new ProviderRequestContext;

    expect($context->provider)->toBeNull();
    expect($context->model)->toBeNull();
    expect($context->correlationId)->toBeNull();
});

it('carries the provider and model it is given', function () {
    $context = new ProviderRequestContext('openai', 'gpt-4o');

    expect($context->provider)->toBe('openai');
    expect($context->model)->toBe('gpt-4o');
    expect($context->correlationId)->toBeNull();
});

it('withCorrelationId returns a new instance that preserves provider and model', function () {
    $context = new ProviderRequestContext('anthropic', 'claude-sonnet-4-5');

    $stamped = $context->withCorrelationId('abc-123');

    expect($stamped)->not->toBe($context);
    expect($stamped->provider)->toBe('anthropic');
    expect($stamped->model)->toBe('claude-sonnet-4-5');
    expect($stamped->correlationId)->toBe('abc-123');
});

it('withCorrelationId leaves the original untouched', function () {
    $context = new ProviderRequestContext('xai', 'grok-3-mini');

    $context->withCorrelationId('id-1');

    expect($context->correlationId)->toBeNull();
});
