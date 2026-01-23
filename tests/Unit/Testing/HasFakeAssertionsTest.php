<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Atlasphp\Atlas\Testing\Concerns\HasFakeAssertions;
use Atlasphp\Atlas\Testing\Support\RecordedRequest;
use PHPUnit\Framework\AssertionFailedError;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

// Test class that uses the trait
class TestClassWithAssertions
{
    use HasFakeAssertions;

    /** @var array<int, RecordedRequest> */
    private array $requests = [];

    public function addRequest(RecordedRequest $request): void
    {
        $this->requests[] = $request;
    }

    protected function getRecordedRequests(): array
    {
        return $this->requests;
    }
}

function createMockAgent(string $key): AgentContract
{
    $agent = Mockery::mock(AgentContract::class);
    $agent->shouldReceive('key')->andReturn($key);

    return $agent;
}

function createRecordedRequest(
    string $agentKey,
    string $input = 'Test input',
    ?ExecutionContext $context = null,
    ?object $schema = null,
): RecordedRequest {
    return new RecordedRequest(
        agent: createMockAgent($agentKey),
        input: $input,
        context: $context,
        schema: $schema,
        response: AgentResponse::text('Response'),
        timestamp: time(),
    );
}

beforeEach(function () {
    $this->assertions = new TestClassWithAssertions;
});

// assertCalled tests

test('assertCalled passes when any agent was called', function () {
    $this->assertions->addRequest(createRecordedRequest('test-agent'));

    $this->assertions->assertCalled();
    expect(true)->toBeTrue(); // If we got here, assertion passed
});

test('assertCalled fails when no agents were called', function () {
    expect(fn () => $this->assertions->assertCalled())
        ->toThrow(AssertionFailedError::class, 'Expected at least one agent to be called');
});

test('assertCalled with agent key passes when that agent was called', function () {
    $this->assertions->addRequest(createRecordedRequest('my-agent'));

    $this->assertions->assertCalled('my-agent');
    expect(true)->toBeTrue();
});

test('assertCalled with agent key fails when that agent was not called', function () {
    $this->assertions->addRequest(createRecordedRequest('other-agent'));

    expect(fn () => $this->assertions->assertCalled('my-agent'))
        ->toThrow(AssertionFailedError::class, "Expected agent 'my-agent' to be called");
});

// assertCalledTimes tests

test('assertCalledTimes passes with correct count', function () {
    $this->assertions->addRequest(createRecordedRequest('test-agent'));
    $this->assertions->addRequest(createRecordedRequest('test-agent'));
    $this->assertions->addRequest(createRecordedRequest('test-agent'));

    $this->assertions->assertCalledTimes('test-agent', 3);
    expect(true)->toBeTrue();
});

test('assertCalledTimes fails with incorrect count', function () {
    $this->assertions->addRequest(createRecordedRequest('test-agent'));
    $this->assertions->addRequest(createRecordedRequest('test-agent'));

    expect(fn () => $this->assertions->assertCalledTimes('test-agent', 3))
        ->toThrow(AssertionFailedError::class, 'called 2 time(s)');
});

test('assertCalledTimes passes with zero when not called', function () {
    $this->assertions->assertCalledTimes('uncalled-agent', 0);
    expect(true)->toBeTrue();
});

// assertNotCalled tests

test('assertNotCalled passes when agent was not called', function () {
    $this->assertions->addRequest(createRecordedRequest('other-agent'));

    $this->assertions->assertNotCalled('my-agent');
    expect(true)->toBeTrue();
});

test('assertNotCalled fails when agent was called', function () {
    $this->assertions->addRequest(createRecordedRequest('my-agent'));

    expect(fn () => $this->assertions->assertNotCalled('my-agent'))
        ->toThrow(AssertionFailedError::class, "Expected agent 'my-agent' not to be called");
});

// assertNothingCalled tests

test('assertNothingCalled passes when no agents were called', function () {
    $this->assertions->assertNothingCalled();
    expect(true)->toBeTrue();
});

test('assertNothingCalled fails when agents were called', function () {
    $this->assertions->addRequest(createRecordedRequest('test-agent'));

    expect(fn () => $this->assertions->assertNothingCalled())
        ->toThrow(AssertionFailedError::class, 'Expected no agents to be called');
});

// assertSent tests

test('assertSent passes when callback returns true', function () {
    $this->assertions->addRequest(createRecordedRequest('test-agent', 'Hello world'));

    $this->assertions->assertSent(fn (RecordedRequest $r) => str_contains($r->input, 'Hello'));
    expect(true)->toBeTrue();
});

test('assertSent fails when no request matches callback', function () {
    $this->assertions->addRequest(createRecordedRequest('test-agent', 'Goodbye'));

    expect(fn () => $this->assertions->assertSent(fn (RecordedRequest $r) => str_contains($r->input, 'Hello')))
        ->toThrow(AssertionFailedError::class, 'Expected a request matching the callback');
});

test('assertSent fails when no requests exist', function () {
    expect(fn () => $this->assertions->assertSent(fn () => true))
        ->toThrow(AssertionFailedError::class, 'Expected a request matching the callback');
});

// assertSentWithContext tests

test('assertSentWithContext passes when metadata key exists', function () {
    $context = new ExecutionContext(metadata: ['user_id' => 123]);
    $this->assertions->addRequest(createRecordedRequest('test-agent', 'Input', $context));

    $this->assertions->assertSentWithContext('user_id');
    expect(true)->toBeTrue();
});

test('assertSentWithContext passes when metadata key and value match', function () {
    $context = new ExecutionContext(metadata: ['user_id' => 123]);
    $this->assertions->addRequest(createRecordedRequest('test-agent', 'Input', $context));

    $this->assertions->assertSentWithContext('user_id', 123);
    expect(true)->toBeTrue();
});

test('assertSentWithContext fails when metadata key does not exist', function () {
    $context = new ExecutionContext(metadata: ['other_key' => 'value']);
    $this->assertions->addRequest(createRecordedRequest('test-agent', 'Input', $context));

    expect(fn () => $this->assertions->assertSentWithContext('user_id'))
        ->toThrow(AssertionFailedError::class);
});

// assertSentWithSchema tests

test('assertSentWithSchema passes when schema was provided', function () {
    $schema = new ObjectSchema(
        name: 'test',
        description: 'Test schema',
        properties: [new StringSchema('field', 'A field')],
        requiredFields: ['field'],
    );
    $this->assertions->addRequest(createRecordedRequest('test-agent', 'Input', null, $schema));

    $this->assertions->assertSentWithSchema();
    expect(true)->toBeTrue();
});

test('assertSentWithSchema fails when no schema was provided', function () {
    $this->assertions->addRequest(createRecordedRequest('test-agent'));

    expect(fn () => $this->assertions->assertSentWithSchema())
        ->toThrow(AssertionFailedError::class);
});

// assertSentWithInput tests

test('assertSentWithInput passes when input contains needle', function () {
    $this->assertions->addRequest(createRecordedRequest('test-agent', 'Hello world'));

    $this->assertions->assertSentWithInput('world');
    expect(true)->toBeTrue();
});

test('assertSentWithInput fails when input does not contain needle', function () {
    $this->assertions->addRequest(createRecordedRequest('test-agent', 'Goodbye'));

    expect(fn () => $this->assertions->assertSentWithInput('Hello'))
        ->toThrow(AssertionFailedError::class);
});
