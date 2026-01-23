<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Services\ModerationService;
use Atlasphp\Atlas\Providers\Support\ModerationResponse;
use Atlasphp\Atlas\Providers\Support\ModerationResult;
use Atlasphp\Atlas\Providers\Support\PendingModerationRequest;

beforeEach(function () {
    $this->moderationService = Mockery::mock(ModerationService::class);

    $this->request = new PendingModerationRequest($this->moderationService);
});

afterEach(function () {
    Mockery::close();
});

function createMockModerationResponse(): ModerationResponse
{
    $result = new ModerationResult(
        false,
        ['violence' => false, 'hate' => false],
        ['violence' => 0.01, 'hate' => 0.01],
    );

    return new ModerationResponse([$result], 'mod-123', 'omni-moderation-latest');
}

test('withProvider returns new instance with provider', function () {
    $result = $this->request->withProvider('openai');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingModerationRequest::class);
});

test('withModel returns new instance with model', function () {
    $result = $this->request->withModel('text-moderation-latest');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingModerationRequest::class);
});

test('withProvider with model returns new instance with both', function () {
    $result = $this->request->withProvider('openai', 'omni-moderation-latest');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingModerationRequest::class);
});

test('withProviderOptions returns new instance with options', function () {
    $result = $this->request->withProviderOptions(['custom' => true]);

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingModerationRequest::class);
});

test('withMetadata returns new instance with metadata', function () {
    $result = $this->request->withMetadata(['user_id' => 123]);

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingModerationRequest::class);
});

test('withRetry returns new instance with retry config', function () {
    $result = $this->request->withRetry(3, 1000);

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingModerationRequest::class);
});

test('moderate calls service with empty options when no config', function () {
    $expected = createMockModerationResponse();

    $this->moderationService
        ->shouldReceive('moderate')
        ->once()
        ->with('Hello world', [], null)
        ->andReturn($expected);

    $result = $this->request->moderate('Hello world');

    expect($result)->toBe($expected);
});

test('moderate passes provider to service options', function () {
    $expected = createMockModerationResponse();

    $this->moderationService
        ->shouldReceive('moderate')
        ->once()
        ->with('Hello world', Mockery::on(function ($options) {
            return $options['provider'] === 'anthropic';
        }), null)
        ->andReturn($expected);

    $result = $this->request->withProvider('anthropic')->moderate('Hello world');

    expect($result)->toBe($expected);
});

test('moderate passes model to service options', function () {
    $expected = createMockModerationResponse();

    $this->moderationService
        ->shouldReceive('moderate')
        ->once()
        ->with('Hello world', Mockery::on(function ($options) {
            return $options['model'] === 'text-moderation-latest';
        }), null)
        ->andReturn($expected);

    $result = $this->request->withModel('text-moderation-latest')->moderate('Hello world');

    expect($result)->toBe($expected);
});

test('moderate passes provider and model together to service options', function () {
    $expected = createMockModerationResponse();

    $this->moderationService
        ->shouldReceive('moderate')
        ->once()
        ->with('Hello world', Mockery::on(function ($options) {
            return $options['provider'] === 'openai' && $options['model'] === 'omni-moderation-latest';
        }), null)
        ->andReturn($expected);

    $result = $this->request->withProvider('openai', 'omni-moderation-latest')->moderate('Hello world');

    expect($result)->toBe($expected);
});

test('moderate passes provider options to service options', function () {
    $expected = createMockModerationResponse();

    $this->moderationService
        ->shouldReceive('moderate')
        ->once()
        ->with('Hello world', Mockery::on(function ($options) {
            return isset($options['provider_options']) && $options['provider_options']['custom'] === true;
        }), null)
        ->andReturn($expected);

    $result = $this->request->withProviderOptions(['custom' => true])->moderate('Hello world');

    expect($result)->toBe($expected);
});

test('moderate passes metadata to service options', function () {
    $expected = createMockModerationResponse();
    $metadata = ['user_id' => 123];

    $this->moderationService
        ->shouldReceive('moderate')
        ->once()
        ->with('Hello world', Mockery::on(function ($options) use ($metadata) {
            return isset($options['metadata']) && $options['metadata'] === $metadata;
        }), null)
        ->andReturn($expected);

    $result = $this->request->withMetadata($metadata)->moderate('Hello world');

    expect($result)->toBe($expected);
});

test('moderate passes retry config to service', function () {
    $expected = createMockModerationResponse();
    $retryConfig = [3, 1000, null, true];

    $this->moderationService
        ->shouldReceive('moderate')
        ->once()
        ->with('Hello world', [], $retryConfig)
        ->andReturn($expected);

    $result = $this->request->withRetry(3, 1000)->moderate('Hello world');

    expect($result)->toBe($expected);
});

test('moderate accepts array input for batch moderation', function () {
    $expected = createMockModerationResponse();
    $inputs = ['Hello world', 'Another text'];

    $this->moderationService
        ->shouldReceive('moderate')
        ->once()
        ->with($inputs, [], null)
        ->andReturn($expected);

    $result = $this->request->moderate($inputs);

    expect($result)->toBe($expected);
});

test('moderate passes all options to service with array input', function () {
    $expected = createMockModerationResponse();
    $inputs = ['Hello world', 'Another text'];
    $metadata = ['user_id' => 123];

    $this->moderationService
        ->shouldReceive('moderate')
        ->once()
        ->with($inputs, Mockery::on(function ($options) use ($metadata) {
            return $options['provider'] === 'openai'
                && $options['model'] === 'omni-moderation-latest'
                && isset($options['metadata']) && $options['metadata'] === $metadata;
        }), [3, 1000, null, true])
        ->andReturn($expected);

    $result = $this->request
        ->withProvider('openai', 'omni-moderation-latest')
        ->withMetadata($metadata)
        ->withRetry(3, 1000)
        ->moderate($inputs);

    expect($result)->toBe($expected);
});

test('chaining preserves all config', function () {
    $expected = createMockModerationResponse();
    $metadata = ['user_id' => 123];

    $this->moderationService
        ->shouldReceive('moderate')
        ->once()
        ->with('Hello world', Mockery::on(function ($options) use ($metadata) {
            return $options['provider'] === 'openai'
                && $options['model'] === 'omni-moderation-latest'
                && isset($options['provider_options']) && $options['provider_options']['custom'] === true
                && isset($options['metadata']) && $options['metadata'] === $metadata;
        }), [3, 1000, null, true])
        ->andReturn($expected);

    $result = $this->request
        ->withProvider('openai', 'omni-moderation-latest')
        ->withProviderOptions(['custom' => true])
        ->withMetadata($metadata)
        ->withRetry(3, 1000)
        ->moderate('Hello world');

    expect($result)->toBe($expected);
});

test('moderate merges additional options', function () {
    $expected = createMockModerationResponse();
    $additionalOptions = ['extra_option' => true];

    $this->moderationService
        ->shouldReceive('moderate')
        ->once()
        ->with('Hello world', Mockery::on(function ($options) {
            return isset($options['extra_option']) && $options['extra_option'] === true;
        }), null)
        ->andReturn($expected);

    $result = $this->request->moderate('Hello world', $additionalOptions);

    expect($result)->toBe($expected);
});

test('fluent config overrides additional options', function () {
    $expected = createMockModerationResponse();

    $this->moderationService
        ->shouldReceive('moderate')
        ->once()
        ->with('Hello world', Mockery::on(function ($options) {
            // Fluent config should override additional options
            return $options['provider'] === 'anthropic';
        }), null)
        ->andReturn($expected);

    // Provider from fluent should override provider from additional options
    $result = $this->request
        ->withProvider('anthropic')
        ->moderate('Hello world', ['provider' => 'openai']);

    expect($result)->toBe($expected);
});
