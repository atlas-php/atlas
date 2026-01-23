<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Exceptions\AgentException;
use Atlasphp\Atlas\Agents\Services\AgentExecutor;
use Atlasphp\Atlas\Agents\Services\SystemPromptBuilder;
use Atlasphp\Atlas\Foundation\Services\PipelineRegistry;
use Atlasphp\Atlas\Foundation\Services\PipelineRunner;
use Atlasphp\Atlas\Providers\Contracts\PrismBuilderContract;
use Atlasphp\Atlas\Providers\Exceptions\ProviderOverloadedException;
use Atlasphp\Atlas\Providers\Exceptions\RateLimitedException;
use Atlasphp\Atlas\Providers\Exceptions\RequestTooLargeException;
use Atlasphp\Atlas\Providers\Exceptions\StructuredDecodingException;
use Atlasphp\Atlas\Providers\Services\ProviderConfigService;
use Atlasphp\Atlas\Providers\Services\UsageExtractorRegistry;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Atlasphp\Atlas\Tools\Services\ToolBuilder;
use Atlasphp\Atlas\Tools\Services\ToolExecutor;
use Atlasphp\Atlas\Tools\Services\ToolRegistry;
use Illuminate\Container\Container;
use Prism\Prism\Exceptions\PrismProviderOverloadedException;
use Prism\Prism\Exceptions\PrismRateLimitedException;
use Prism\Prism\Exceptions\PrismRequestTooLargeException;
use Prism\Prism\Exceptions\PrismStructuredDecodingException;

beforeEach(function () {
    $this->container = new Container;
    $registry = new PipelineRegistry;
    $this->runner = new PipelineRunner($registry, $this->container);
    $this->prismBuilder = Mockery::mock(PrismBuilderContract::class);
    $this->systemPromptBuilder = new SystemPromptBuilder($this->runner);
    $toolRegistry = new ToolRegistry($this->container);
    $toolExecutor = new ToolExecutor($this->runner);
    $this->toolBuilder = new ToolBuilder($toolRegistry, $toolExecutor, $this->container);
    $this->usageExtractor = new UsageExtractorRegistry;
    $this->configService = Mockery::mock(ProviderConfigService::class);
    $this->configService->shouldReceive('getRetryConfig')->andReturn(null);
});

afterEach(function () {
    Mockery::close();
});

test('it converts PrismRateLimitedException to RateLimitedException', function () {
    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->andThrow(new PrismRateLimitedException([], 60));

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
        $this->configService,
    );

    $agent = new TestAgent;

    expect(fn () => $executor->execute($agent, 'Hello'))
        ->toThrow(RateLimitedException::class);
});

test('it converts PrismProviderOverloadedException to ProviderOverloadedException', function () {
    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->andThrow(new PrismProviderOverloadedException('openai'));

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
        $this->configService,
    );

    $agent = new TestAgent;

    expect(fn () => $executor->execute($agent, 'Hello'))
        ->toThrow(ProviderOverloadedException::class);
});

test('it converts PrismRequestTooLargeException to RequestTooLargeException', function () {
    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->andThrow(new PrismRequestTooLargeException('anthropic'));

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
        $this->configService,
    );

    $agent = new TestAgent;

    expect(fn () => $executor->execute($agent, 'Hello'))
        ->toThrow(RequestTooLargeException::class);
});

test('it converts PrismStructuredDecodingException to StructuredDecodingException', function () {
    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->andThrow(new PrismStructuredDecodingException('invalid json'));

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
        $this->configService,
    );

    $agent = new TestAgent;

    expect(fn () => $executor->execute($agent, 'Hello'))
        ->toThrow(StructuredDecodingException::class);
});

test('it wraps non-Prism exceptions in AgentException', function () {
    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->andThrow(new RuntimeException('Generic error'));

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
        $this->configService,
    );

    $agent = new TestAgent;

    expect(fn () => $executor->execute($agent, 'Hello'))
        ->toThrow(AgentException::class);
});

test('RateLimitedException preserves retry after from Prism exception', function () {
    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->andThrow(new PrismRateLimitedException([], 120));

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
        $this->configService,
    );

    $agent = new TestAgent;

    try {
        $executor->execute($agent, 'Hello');
        $this->fail('Expected RateLimitedException');
    } catch (RateLimitedException $e) {
        expect($e->retryAfter())->toBe(120);
        expect($e->getPrevious())->toBeInstanceOf(PrismRateLimitedException::class);
    }
});
