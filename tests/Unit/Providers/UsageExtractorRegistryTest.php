<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Contracts\UsageExtractorContract;
use Atlasphp\Atlas\Providers\Services\UsageExtractorRegistry;
use Atlasphp\Atlas\Providers\Support\DefaultUsageExtractor;

beforeEach(function () {
    $this->registry = new UsageExtractorRegistry;
});

test('it registers an extractor', function () {
    $extractor = Mockery::mock(UsageExtractorContract::class);
    $extractor->shouldReceive('provider')->andReturn('openai');

    $result = $this->registry->register($extractor);

    expect($result)->toBe($this->registry);
    expect($this->registry->forProvider('openai'))->toBe($extractor);
});

test('it returns registered extractor for provider', function () {
    $extractor = Mockery::mock(UsageExtractorContract::class);
    $extractor->shouldReceive('provider')->andReturn('openai');

    $this->registry->register($extractor);

    expect($this->registry->forProvider('openai'))->toBe($extractor);
});

test('it returns default extractor for unknown provider', function () {
    $extractor = $this->registry->forProvider('unknown-provider');

    expect($extractor)->toBeInstanceOf(DefaultUsageExtractor::class);
});

test('it extracts usage using registered extractor', function () {
    $extractor = Mockery::mock(UsageExtractorContract::class);
    $extractor->shouldReceive('provider')->andReturn('openai');
    $extractor->shouldReceive('extract')
        ->with(['tokens' => 100])
        ->andReturn(['prompt_tokens' => 100]);

    $this->registry->register($extractor);

    $result = $this->registry->extract('openai', ['tokens' => 100]);

    expect($result)->toBe(['prompt_tokens' => 100]);
});

test('it extracts usage using default extractor for unknown provider', function () {
    $response = ['usage' => ['total_tokens' => 50]];

    $result = $this->registry->extract('unknown', $response);

    expect($result)->toBeArray();
});

test('it supports method chaining', function () {
    $extractor1 = Mockery::mock(UsageExtractorContract::class);
    $extractor1->shouldReceive('provider')->andReturn('openai');

    $extractor2 = Mockery::mock(UsageExtractorContract::class);
    $extractor2->shouldReceive('provider')->andReturn('anthropic');

    $result = $this->registry
        ->register($extractor1)
        ->register($extractor2);

    expect($result)->toBeInstanceOf(UsageExtractorRegistry::class);
});
