<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Services\AgentExecutor;
use Atlasphp\Atlas\Agents\Services\SystemPromptBuilder;
use Atlasphp\Atlas\Foundation\Services\PipelineRegistry;
use Atlasphp\Atlas\Foundation\Services\PipelineRunner;
use Atlasphp\Atlas\Providers\Contracts\PrismBuilderContract;
use Atlasphp\Atlas\Providers\Services\ProviderConfigService;
use Atlasphp\Atlas\Providers\Services\UsageExtractorRegistry;
use Atlasphp\Atlas\Streaming\Events\ArtifactEvent;
use Atlasphp\Atlas\Streaming\Events\CitationEvent;
use Atlasphp\Atlas\Streaming\Events\StepFinishEvent;
use Atlasphp\Atlas\Streaming\Events\StepStartEvent;
use Atlasphp\Atlas\Streaming\Events\ThinkingCompleteEvent;
use Atlasphp\Atlas\Streaming\Events\ThinkingDeltaEvent;
use Atlasphp\Atlas\Streaming\Events\ThinkingStartEvent;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Atlasphp\Atlas\Tools\Services\ToolBuilder;
use Atlasphp\Atlas\Tools\Services\ToolExecutor;
use Atlasphp\Atlas\Tools\Services\ToolRegistry;
use Illuminate\Container\Container;
use Prism\Prism\Enums\Citations\CitationSourcePositionType;
use Prism\Prism\Enums\Citations\CitationSourceType;
use Prism\Prism\Streaming\Events\ArtifactEvent as PrismArtifactEvent;
use Prism\Prism\Streaming\Events\CitationEvent as PrismCitationEvent;
use Prism\Prism\Streaming\Events\StepFinishEvent as PrismStepFinishEvent;
use Prism\Prism\Streaming\Events\StepStartEvent as PrismStepStartEvent;
use Prism\Prism\Streaming\Events\StreamEndEvent as PrismStreamEndEvent;
use Prism\Prism\Streaming\Events\ThinkingCompleteEvent as PrismThinkingCompleteEvent;
use Prism\Prism\Streaming\Events\ThinkingEvent as PrismThinkingEvent;
use Prism\Prism\Streaming\Events\ThinkingStartEvent as PrismThinkingStartEvent;
use Prism\Prism\ValueObjects\Artifact;
use Prism\Prism\ValueObjects\Citation;

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

test('it converts ThinkingStartEvent', function () {
    $events = [];

    // Create mock stream with ThinkingStartEvent
    $mockStream = (function () {
        yield new PrismThinkingStartEvent(
            id: 'evt_1',
            timestamp: 1234567890,
            reasoningId: 'reason_abc',
        );
        yield new PrismStreamEndEvent(
            id: 'evt_2',
            timestamp: 1234567891,
            finishReason: \Prism\Prism\Enums\FinishReason::Stop,
            usage: new \Prism\Prism\ValueObjects\Usage(10, 20),
        );
    })();

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asStream')->andReturn($mockStream);

    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->andReturn($mockPendingRequest);

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
        $this->configService,
    );

    $agent = new TestAgent;
    $streamResponse = $executor->stream($agent, 'Hello');

    foreach ($streamResponse as $event) {
        $events[] = $event;
    }

    $thinkingEvent = array_filter($events, fn ($e) => $e instanceof ThinkingStartEvent);
    expect($thinkingEvent)->toHaveCount(1);
    expect(array_values($thinkingEvent)[0]->reasoningId)->toBe('reason_abc');
});

test('it converts ThinkingDeltaEvent', function () {
    $events = [];

    $mockStream = (function () {
        yield new PrismThinkingEvent(
            id: 'evt_1',
            timestamp: 1234567890,
            delta: 'Let me think...',
            reasoningId: 'reason_abc',
            summary: ['key' => 'value'],
        );
        yield new PrismStreamEndEvent(
            id: 'evt_2',
            timestamp: 1234567891,
            finishReason: \Prism\Prism\Enums\FinishReason::Stop,
            usage: new \Prism\Prism\ValueObjects\Usage(10, 20),
        );
    })();

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asStream')->andReturn($mockStream);

    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->andReturn($mockPendingRequest);

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
        $this->configService,
    );

    $agent = new TestAgent;
    $streamResponse = $executor->stream($agent, 'Hello');

    foreach ($streamResponse as $event) {
        $events[] = $event;
    }

    $thinkingEvent = array_filter($events, fn ($e) => $e instanceof ThinkingDeltaEvent);
    expect($thinkingEvent)->toHaveCount(1);
    $event = array_values($thinkingEvent)[0];
    expect($event->delta)->toBe('Let me think...');
    expect($event->reasoningId)->toBe('reason_abc');
    expect($event->summary)->toBe(['key' => 'value']);
});

test('it converts ThinkingCompleteEvent', function () {
    $events = [];

    $mockStream = (function () {
        yield new PrismThinkingCompleteEvent(
            id: 'evt_1',
            timestamp: 1234567890,
            reasoningId: 'reason_abc',
            summary: ['conclusion' => 'done'],
        );
        yield new PrismStreamEndEvent(
            id: 'evt_2',
            timestamp: 1234567891,
            finishReason: \Prism\Prism\Enums\FinishReason::Stop,
            usage: new \Prism\Prism\ValueObjects\Usage(10, 20),
        );
    })();

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asStream')->andReturn($mockStream);

    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->andReturn($mockPendingRequest);

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
        $this->configService,
    );

    $agent = new TestAgent;
    $streamResponse = $executor->stream($agent, 'Hello');

    foreach ($streamResponse as $event) {
        $events[] = $event;
    }

    $thinkingEvent = array_filter($events, fn ($e) => $e instanceof ThinkingCompleteEvent);
    expect($thinkingEvent)->toHaveCount(1);
    $event = array_values($thinkingEvent)[0];
    expect($event->reasoningId)->toBe('reason_abc');
    expect($event->summary)->toBe(['conclusion' => 'done']);
});

test('it converts CitationEvent', function () {
    $events = [];

    $citation = new Citation(
        sourceType: CitationSourceType::Document,
        source: 1,
        sourceText: 'Some text',
        sourceTitle: 'Document Title',
        sourcePositionType: CitationSourcePositionType::PageRange,
        sourceStartIndex: 0,
        sourceEndIndex: 100,
        additionalContent: [],
    );

    $mockStream = (function () use ($citation) {
        yield new PrismCitationEvent(
            id: 'evt_1',
            timestamp: 1234567890,
            citation: $citation,
            messageId: 'msg_123',
            blockIndex: 1,
            metadata: ['confidence' => 0.95],
        );
        yield new PrismStreamEndEvent(
            id: 'evt_2',
            timestamp: 1234567891,
            finishReason: \Prism\Prism\Enums\FinishReason::Stop,
            usage: new \Prism\Prism\ValueObjects\Usage(10, 20),
        );
    })();

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asStream')->andReturn($mockStream);

    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->andReturn($mockPendingRequest);

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
        $this->configService,
    );

    $agent = new TestAgent;
    $streamResponse = $executor->stream($agent, 'Hello');

    foreach ($streamResponse as $event) {
        $events[] = $event;
    }

    $citationEvent = array_filter($events, fn ($e) => $e instanceof CitationEvent);
    expect($citationEvent)->toHaveCount(1);
    $event = array_values($citationEvent)[0];
    expect($event->messageId)->toBe('msg_123');
    expect($event->blockIndex)->toBe(1);
    expect($event->metadata)->toBe(['confidence' => 0.95]);
    expect($event->citation['source_type'])->toBe('document');
    expect($event->citation['source'])->toBe(1);
});

test('it converts ArtifactEvent', function () {
    $events = [];

    $artifact = new Artifact(
        data: base64_encode('<?php echo "hello";'),
        mimeType: 'text/x-php',
        metadata: ['language' => 'php'],
        id: 'art_123',
    );

    $mockStream = (function () use ($artifact) {
        yield new PrismArtifactEvent(
            id: 'evt_1',
            timestamp: 1234567890,
            artifact: $artifact,
            toolCallId: 'call_abc',
            toolName: 'code_gen',
            messageId: 'msg_xyz',
        );
        yield new PrismStreamEndEvent(
            id: 'evt_2',
            timestamp: 1234567891,
            finishReason: \Prism\Prism\Enums\FinishReason::Stop,
            usage: new \Prism\Prism\ValueObjects\Usage(10, 20),
        );
    })();

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asStream')->andReturn($mockStream);

    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->andReturn($mockPendingRequest);

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
        $this->configService,
    );

    $agent = new TestAgent;
    $streamResponse = $executor->stream($agent, 'Hello');

    foreach ($streamResponse as $event) {
        $events[] = $event;
    }

    $artifactEvent = array_filter($events, fn ($e) => $e instanceof ArtifactEvent);
    expect($artifactEvent)->toHaveCount(1);
    $event = array_values($artifactEvent)[0];
    expect($event->toolCallId)->toBe('call_abc');
    expect($event->toolName)->toBe('code_gen');
    expect($event->messageId)->toBe('msg_xyz');
    expect($event->artifact['mime_type'])->toBe('text/x-php');
});

test('it converts StepStartEvent', function () {
    $events = [];

    $mockStream = (function () {
        yield new PrismStepStartEvent(
            id: 'evt_1',
            timestamp: 1234567890,
        );
        yield new PrismStreamEndEvent(
            id: 'evt_2',
            timestamp: 1234567891,
            finishReason: \Prism\Prism\Enums\FinishReason::Stop,
            usage: new \Prism\Prism\ValueObjects\Usage(10, 20),
        );
    })();

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asStream')->andReturn($mockStream);

    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->andReturn($mockPendingRequest);

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
        $this->configService,
    );

    $agent = new TestAgent;
    $streamResponse = $executor->stream($agent, 'Hello');

    foreach ($streamResponse as $event) {
        $events[] = $event;
    }

    $stepEvent = array_filter($events, fn ($e) => $e instanceof StepStartEvent);
    expect($stepEvent)->toHaveCount(1);
});

test('it converts StepFinishEvent', function () {
    $events = [];

    $mockStream = (function () {
        yield new PrismStepFinishEvent(
            id: 'evt_1',
            timestamp: 1234567890,
        );
        yield new PrismStreamEndEvent(
            id: 'evt_2',
            timestamp: 1234567891,
            finishReason: \Prism\Prism\Enums\FinishReason::Stop,
            usage: new \Prism\Prism\ValueObjects\Usage(10, 20),
        );
    })();

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTools')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asStream')->andReturn($mockStream);

    $this->prismBuilder
        ->shouldReceive('forPrompt')
        ->andReturn($mockPendingRequest);

    $executor = new AgentExecutor(
        $this->prismBuilder,
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->usageExtractor,
        $this->configService,
    );

    $agent = new TestAgent;
    $streamResponse = $executor->stream($agent, 'Hello');

    foreach ($streamResponse as $event) {
        $events[] = $event;
    }

    $stepEvent = array_filter($events, fn ($e) => $e instanceof StepFinishEvent);
    expect($stepEvent)->toHaveCount(1);
});
