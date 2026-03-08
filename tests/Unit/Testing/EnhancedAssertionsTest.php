<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Support\AgentContext;
use Atlasphp\Atlas\Testing\AtlasFake;
use Atlasphp\Atlas\Testing\Support\RecordedRequest;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Atlasphp\Atlas\Tests\Fixtures\TestTool;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use PHPUnit\Framework\AssertionFailedError;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Text\Response as PrismResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

function createEnhancedTestResponse(string $text = 'Response'): PrismResponse
{
    return new PrismResponse(
        steps: new Collection,
        text: $text,
        finishReason: FinishReason::Stop,
        toolCalls: [],
        toolResults: [],
        usage: new Usage(10, 20),
        meta: new Meta('req-123', 'gpt-4'),
        messages: new Collection,
    );
}

beforeEach(function () {
    $this->container = new Container;
    $this->fake = new AtlasFake($this->container);
    $this->agent = new TestAgent;
});

// === assertStreamed / assertNotStreamed ===

test('assertStreamed passes when agent used streaming', function () {
    $response = createEnhancedTestResponse();
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $executor->stream($this->agent, 'Hello', new AgentContext);

    $this->fake->assertStreamed('test-agent');
});

test('assertStreamed fails when agent did not use streaming', function () {
    $response = createEnhancedTestResponse();
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $executor->execute($this->agent, 'Hello', new AgentContext);

    expect(fn () => $this->fake->assertStreamed('test-agent'))
        ->toThrow(AssertionFailedError::class);
});

test('assertNotStreamed passes when agent used blocking execution', function () {
    $response = createEnhancedTestResponse();
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $executor->execute($this->agent, 'Hello', new AgentContext);

    $this->fake->assertNotStreamed('test-agent');
});

test('assertNotStreamed fails when agent used streaming', function () {
    $response = createEnhancedTestResponse();
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $executor->stream($this->agent, 'Hello', new AgentContext);

    expect(fn () => $this->fake->assertNotStreamed('test-agent'))
        ->toThrow(AssertionFailedError::class);
});

// === assertCalledWithVariables ===

test('assertCalledWithVariables passes when variables match', function () {
    $response = createEnhancedTestResponse();
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $context = new AgentContext(variables: ['user' => 'Alice', 'role' => 'admin']);
    $executor->execute($this->agent, 'Hello', $context);

    $this->fake->assertCalledWithVariables('test-agent', ['user' => 'Alice']);
});

test('assertCalledWithVariables fails when variables do not match', function () {
    $response = createEnhancedTestResponse();
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $context = new AgentContext(variables: ['user' => 'Alice']);
    $executor->execute($this->agent, 'Hello', $context);

    expect(fn () => $this->fake->assertCalledWithVariables('test-agent', ['user' => 'Bob']))
        ->toThrow(AssertionFailedError::class);
});

// === assertCalledWithProvider ===

test('assertCalledWithProvider passes when provider matches', function () {
    $response = createEnhancedTestResponse();
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $context = new AgentContext(providerOverride: 'anthropic');
    $executor->execute($this->agent, 'Hello', $context);

    $this->fake->assertCalledWithProvider('test-agent', 'anthropic');
});

test('assertCalledWithProvider fails when provider does not match', function () {
    $response = createEnhancedTestResponse();
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $context = new AgentContext(providerOverride: 'openai');
    $executor->execute($this->agent, 'Hello', $context);

    expect(fn () => $this->fake->assertCalledWithProvider('test-agent', 'anthropic'))
        ->toThrow(AssertionFailedError::class);
});

// === assertCalledWithModel ===

test('assertCalledWithModel passes when model matches', function () {
    $response = createEnhancedTestResponse();
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $context = new AgentContext(modelOverride: 'gpt-4o');
    $executor->execute($this->agent, 'Hello', $context);

    $this->fake->assertCalledWithModel('test-agent', 'gpt-4o');
});

// === assertCalledWithTools ===

test('assertCalledWithTools passes when tools match', function () {
    $response = createEnhancedTestResponse();
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $context = new AgentContext(tools: [TestTool::class]);
    $executor->execute($this->agent, 'Hello', $context);

    $this->fake->assertCalledWithTools('test-agent', [TestTool::class]);
});

test('assertCalledWithTools fails when tools do not match', function () {
    $response = createEnhancedTestResponse();
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $context = new AgentContext(tools: []);
    $executor->execute($this->agent, 'Hello', $context);

    expect(fn () => $this->fake->assertCalledWithTools('test-agent', [TestTool::class]))
        ->toThrow(AssertionFailedError::class);
});

// === respondUsing ===

test('respondUsing returns dynamic string response', function () {
    $this->fake->respondUsing('test-agent', function (RecordedRequest $r) {
        return 'Dynamic: '.$r->input;
    });
    $this->fake->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $response = $executor->execute($this->agent, 'Hello world', new AgentContext);

    expect($response->text())->toBe('Dynamic: Hello world');
});

test('respondUsing returns dynamic PrismResponse', function () {
    $this->fake->respondUsing('test-agent', function (RecordedRequest $r) {
        return createEnhancedTestResponse('Custom: '.$r->input);
    });
    $this->fake->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $response = $executor->execute($this->agent, 'Test', new AgentContext);

    expect($response->text())->toBe('Custom: Test');
});

test('respondUsing works with streaming', function () {
    $this->fake->respondUsing('test-agent', function (RecordedRequest $r) {
        return 'Streamed: '.$r->input;
    });
    $this->fake->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $streamResponse = $executor->stream($this->agent, 'Test', new AgentContext);

    $text = '';
    foreach ($streamResponse as $event) {
        if ($event instanceof \Prism\Prism\Streaming\Events\TextDeltaEvent) {
            $text .= $event->delta;
        }
    }

    expect($text)->toBe('Streamed: Test');
});

// === wasStreamed on RecordedRequest ===

test('wasStreamed is false for execute calls', function () {
    $response = createEnhancedTestResponse();
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $executor->execute($this->agent, 'Hello', new AgentContext);

    $recorded = $this->fake->recorded();
    expect($recorded[0]->wasStreamed)->toBeFalse();
});

test('wasStreamed is true for stream calls', function () {
    $response = createEnhancedTestResponse();
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $executor->stream($this->agent, 'Hello', new AgentContext);

    $recorded = $this->fake->recorded();
    expect($recorded[0]->wasStreamed)->toBeTrue();
});
