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

test('it re-throws AgentException directly without wrapping', function () {
    // Use plain constructor to have a simple message we can verify
    $agentException = new AgentException('Direct agent error');

    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->andThrow($agentException);

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
        $this->fail('Expected AgentException');
    } catch (AgentException $e) {
        // Verify it's the exact same exception, not wrapped
        expect($e)->toBe($agentException);
        expect($e->getMessage())->toBe('Direct agent error');
    }
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

// Streaming exception tests

test('stream re-throws AgentException directly without wrapping', function () {
    // Use plain constructor to have a simple message we can verify
    $agentException = new AgentException('Stream agent error');

    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->andThrow($agentException);

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
        $stream = $executor->stream($agent, 'Hello');
        // Consume the stream to trigger the exception
        foreach ($stream as $event) {
            // ...
        }
        $this->fail('Expected AgentException');
    } catch (AgentException $e) {
        // Verify it's the exact same exception, not wrapped
        expect($e)->toBe($agentException);
        expect($e->getMessage())->toBe('Stream agent error');
    }
});

test('stream converts PrismRateLimitedException to RateLimitedException', function () {
    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->andThrow(new PrismRateLimitedException([], 90));

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
        $stream = $executor->stream($agent, 'Hello');
        foreach ($stream as $event) {
            // ...
        }
        $this->fail('Expected RateLimitedException');
    } catch (RateLimitedException $e) {
        expect($e->retryAfter())->toBe(90);
    }
});

test('stream wraps non-Prism exceptions in AgentException', function () {
    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->andThrow(new RuntimeException('Stream generic error'));

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
        $stream = $executor->stream($agent, 'Hello');
        foreach ($stream as $event) {
            // ...
        }
        $this->fail('Expected AgentException');
    } catch (AgentException $e) {
        expect($e->getMessage())->toContain('Stream generic error');
        expect($e->getPrevious())->toBeInstanceOf(RuntimeException::class);
    }
});
