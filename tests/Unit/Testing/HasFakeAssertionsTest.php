<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Atlasphp\Atlas\Testing\AtlasFake;
use Atlasphp\Atlas\Testing\Support\RecordedRequest;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use PHPUnit\Framework\AssertionFailedError;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Text\Response as PrismResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

function createAssertionTestResponse(string $text): PrismResponse
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

// === assertCalled ===

test('assertCalled passes when any agent was called', function () {
    $response = createAssertionTestResponse('Response');
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $executor->execute($this->agent, 'Hello', new ExecutionContext);

    $this->fake->assertCalled();
});

test('assertCalled fails when no agent was called', function () {
    $this->fake->activate();

    $this->fake->assertCalled();
})->throws(AssertionFailedError::class, 'Expected at least one agent to be called');

test('assertCalled with agent key passes when that agent was called', function () {
    $response = createAssertionTestResponse('Response');
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $executor->execute($this->agent, 'Hello', new ExecutionContext);

    $this->fake->assertCalled('test-agent');
});

test('assertCalled with agent key fails when different agent was called', function () {
    $response = createAssertionTestResponse('Response');
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $executor->execute($this->agent, 'Hello', new ExecutionContext);

    $this->fake->assertCalled('other-agent');
})->throws(AssertionFailedError::class, "Expected agent 'other-agent' to be called");

// === assertCalledTimes ===

test('assertCalledTimes passes with correct count', function () {
    $response = createAssertionTestResponse('Response');
    $this->fake->sequence([$response, $response, $response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $executor->execute($this->agent, 'First', new ExecutionContext);
    $executor->execute($this->agent, 'Second', new ExecutionContext);

    $this->fake->assertCalledTimes('test-agent', 2);
});

test('assertCalledTimes fails with wrong count', function () {
    $response = createAssertionTestResponse('Response');
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $executor->execute($this->agent, 'Hello', new ExecutionContext);

    $this->fake->assertCalledTimes('test-agent', 3);
})->throws(AssertionFailedError::class, 'to be called 3 time(s), but it was called 1 time(s)');

test('assertCalledTimes with zero passes when not called', function () {
    $this->fake->activate();

    $this->fake->assertCalledTimes('test-agent', 0);
});

// === assertNotCalled ===

test('assertNotCalled passes when agent was not called', function () {
    $this->fake->activate();

    $this->fake->assertNotCalled('test-agent');
});

test('assertNotCalled fails when agent was called', function () {
    $response = createAssertionTestResponse('Response');
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $executor->execute($this->agent, 'Hello', new ExecutionContext);

    $this->fake->assertNotCalled('test-agent');
})->throws(AssertionFailedError::class, "Expected agent 'test-agent' not to be called");

test('assertNotCalled passes for different agent', function () {
    $response = createAssertionTestResponse('Response');
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $executor->execute($this->agent, 'Hello', new ExecutionContext);

    $this->fake->assertNotCalled('other-agent');
});

// === assertNothingCalled ===

test('assertNothingCalled passes when nothing called', function () {
    $this->fake->activate();

    $this->fake->assertNothingCalled();
});

test('assertNothingCalled fails when something called', function () {
    $response = createAssertionTestResponse('Response');
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $executor->execute($this->agent, 'Hello', new ExecutionContext);

    $this->fake->assertNothingCalled();
})->throws(AssertionFailedError::class, 'Expected no agents to be called');

// === assertSent ===

test('assertSent passes when callback returns true', function () {
    $response = createAssertionTestResponse('Response');
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $executor->execute($this->agent, 'Hello world', new ExecutionContext);

    $this->fake->assertSent(function (RecordedRequest $request) {
        return $request->input === 'Hello world';
    });
});

test('assertSent fails when callback never returns true', function () {
    $response = createAssertionTestResponse('Response');
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $executor->execute($this->agent, 'Hello', new ExecutionContext);

    $this->fake->assertSent(function (RecordedRequest $request) {
        return $request->input === 'Something else';
    });
})->throws(AssertionFailedError::class, 'Expected a request matching the callback');

test('assertSent checks multiple requests', function () {
    $response = createAssertionTestResponse('Response');
    $this->fake->sequence([$response, $response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $executor->execute($this->agent, 'First', new ExecutionContext);
    $executor->execute($this->agent, 'Second', new ExecutionContext);

    // Should find the second request
    $this->fake->assertSent(function (RecordedRequest $request) {
        return $request->input === 'Second';
    });
});

// === assertSentWithContext ===

test('assertSentWithContext passes when metadata key exists', function () {
    $response = createAssertionTestResponse('Response');
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $context = new ExecutionContext(metadata: ['user_id' => 123]);
    $executor->execute($this->agent, 'Hello', $context);

    $this->fake->assertSentWithContext('user_id');
});

test('assertSentWithContext passes when metadata key and value match', function () {
    $response = createAssertionTestResponse('Response');
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $context = new ExecutionContext(metadata: ['user_id' => 123]);
    $executor->execute($this->agent, 'Hello', $context);

    $this->fake->assertSentWithContext('user_id', 123);
});

test('assertSentWithContext fails when metadata key missing', function () {
    $response = createAssertionTestResponse('Response');
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $executor->execute($this->agent, 'Hello', new ExecutionContext);

    $this->fake->assertSentWithContext('user_id');
})->throws(AssertionFailedError::class);

// === assertSentWithSchema ===

test('assertSentWithSchema passes when withSchema was called', function () {
    $response = createAssertionTestResponse('Response');
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $context = new ExecutionContext(prismCalls: [
        ['method' => 'withSchema', 'args' => ['SomeSchema']],
    ]);
    $executor->execute($this->agent, 'Hello', $context);

    $this->fake->assertSentWithSchema();
});

test('assertSentWithSchema fails when withSchema was not called', function () {
    $response = createAssertionTestResponse('Response');
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $executor->execute($this->agent, 'Hello', new ExecutionContext);

    $this->fake->assertSentWithSchema();
})->throws(AssertionFailedError::class);

// === assertSentWithInput ===

test('assertSentWithInput passes when input contains string', function () {
    $response = createAssertionTestResponse('Response');
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $executor->execute($this->agent, 'Hello world', new ExecutionContext);

    $this->fake->assertSentWithInput('world');
});

test('assertSentWithInput fails when input does not contain string', function () {
    $response = createAssertionTestResponse('Response');
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $executor->execute($this->agent, 'Hello', new ExecutionContext);

    $this->fake->assertSentWithInput('world');
})->throws(AssertionFailedError::class);

test('assertSentWithInput is case sensitive', function () {
    $response = createAssertionTestResponse('Response');
    $this->fake->sequence([$response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $executor->execute($this->agent, 'Hello World', new ExecutionContext);

    $this->fake->assertSentWithInput('world');
})->throws(AssertionFailedError::class);

// === Integration tests ===

test('multiple assertions can be combined', function () {
    $response = createAssertionTestResponse('Response');
    $this->fake->sequence([$response, $response])->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $executor->execute($this->agent, 'First request', new ExecutionContext(metadata: ['trace_id' => 'abc']));
    $executor->execute($this->agent, 'Second request', new ExecutionContext);

    $this->fake->assertCalled('test-agent');
    $this->fake->assertCalledTimes('test-agent', 2);
    $this->fake->assertSentWithInput('First');
    $this->fake->assertSentWithContext('trace_id', 'abc');
});
