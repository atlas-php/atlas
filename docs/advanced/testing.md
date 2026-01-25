# Testing

Strategies for testing Atlas-powered applications.

## Testing Philosophy

Atlas is designed for testability:

- **Stateless agents** — No hidden state to manage
- **Dependency injection** — All services are injectable
- **Contract-based** — Mock interfaces, not implementations
- **Pipeline system** — Intercept and modify behavior in tests

## Unit Testing Agents

### Testing Agent Configuration

```php
use App\Agents\CustomerSupportAgent;
use PHPUnit\Framework\TestCase;

class CustomerSupportAgentTest extends TestCase
{
    public function test_agent_has_correct_configuration(): void
    {
        $agent = new CustomerSupportAgent();

        $this->assertEquals('customer-support', $agent->key());
        $this->assertEquals('openai', $agent->provider());
        $this->assertEquals('gpt-4o', $agent->model());
        $this->assertStringContains('customer support', $agent->systemPrompt());
    }

    public function test_agent_has_required_tools(): void
    {
        $agent = new CustomerSupportAgent();
        $tools = $agent->tools();

        $this->assertContains(LookupOrderTool::class, $tools);
        $this->assertContains(SearchProductsTool::class, $tools);
    }
}
```

### Testing System Prompt Variables

```php
public function test_system_prompt_contains_expected_variables(): void
{
    $agent = new CustomerSupportAgent();
    $prompt = $agent->systemPrompt();

    $this->assertStringContains('{user_name}', $prompt);
    $this->assertStringContains('{customer_name}', $prompt);
}
```

## Unit Testing Tools

### Testing Tool Logic

```php
use App\Tools\LookupOrderTool;use Atlasphp\Atlas\Tools\Support\ToolContext;use PHPUnit\Framework\TestCase;

class LookupOrderToolTest extends TestCase
{
    public function test_returns_order_when_found(): void
    {
        // Create a mock order
        $order = Order::factory()->create([
            'id' => 'ORD-123',
            'status' => 'shipped',
        ]);

        $tool = new LookupOrderTool();
        $context = new ToolContext([]);

        $result = $tool->handle(['order_id' => 'ORD-123'], $context);

        $this->assertTrue($result->succeeded());
        $this->assertStringContains('shipped', $result->text);
    }

    public function test_returns_error_when_not_found(): void
    {
        $tool = new LookupOrderTool();
        $context = new ToolContext([]);

        $result = $tool->handle(['order_id' => 'INVALID'], $context);

        $this->assertTrue($result->failed());
        $this->assertStringContains('not found', $result->text);
    }

    public function test_uses_context_metadata_for_authorization(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $tool = new LookupOrderTool();
        $context = new ToolContext(['user_id' => $user->id]);

        $result = $tool->handle(['order_id' => $order->id], $context);

        $this->assertTrue($result->succeeded());
    }
}
```

### Testing Tool Parameters

```php
public function test_tool_has_correct_parameters(): void
{
    $tool = new LookupOrderTool();
    $params = $tool->parameters();

    $this->assertCount(1, $params);
    $this->assertEquals('order_id', $params[0]->name);
    $this->assertTrue($params[0]->required);
}
```

## Integration Testing

### Mocking the AtlasManager

```php
use Atlasphp\Atlas\Providers\Services\AtlasManager;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Mockery;

class ChatControllerTest extends TestCase
{
    public function test_chat_endpoint_returns_response(): void
    {
        // Mock AtlasManager
        $mockManager = Mockery::mock(AtlasManager::class);
        $mockManager->shouldReceive('chat')
            ->once()
            ->andReturn(AgentResponse::text('Hello! How can I help?'));

        $this->app->instance(AtlasManager::class, $mockManager);

        $response = $this->postJson('/api/chat', [
            'message' => 'Hello',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Hello! How can I help?');
    }
}
```

### Testing with Real Responses

For integration tests that need real AI responses:

```php
/**
 * @group integration
 * @group requires-api-key
 */
class AtlasIntegrationTest extends TestCase
{
    public function test_simple_chat_returns_response(): void
    {
        $response = Atlas::agent('test-agent')->chat('Say hello');

        $this->assertTrue($response->hasText());
        $this->assertNotEmpty($response->text);
    }
}
```

## Faking Atlas

Atlas provides a built-in `Atlas::fake()` method for testing, following Laravel's `Http::fake()` pattern:

### Basic Usage

```php
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Agents\Support\AgentResponse;

public function test_chat_returns_expected_response(): void
{
    // Enable fake mode with a default response
    Atlas::fake([
        AgentResponse::text('Hello! How can I help?'),
    ]);

    // Call your code that uses Atlas
    $response = Atlas::agent('support-agent')->chat('Hello');

    // Assert the response
    $this->assertEquals('Hello! How can I help?', $response->text);

    // Assert the agent was called
    Atlas::assertCalled('support-agent');
}
```

### Agent-Specific Responses

Configure different responses for different agents:

```php
Atlas::fake()
    ->forAgent('billing-agent', AgentResponse::text('Your balance is $100'))
    ->forAgent('support-agent', AgentResponse::text('How can I help?'));

$billing = Atlas::agent('billing-agent')->chat('Balance?');
$support = Atlas::agent('support-agent')->chat('Help');

Atlas::assertCalled('billing-agent');
Atlas::assertCalled('support-agent');
```

### Sequential Responses

Return different responses for consecutive calls:

```php
Atlas::fake()
    ->forAgent('assistant')
    ->sequence([
        AgentResponse::text('First response'),
        AgentResponse::text('Second response'),
        AgentResponse::text('Third response'),
    ]);

// Each call returns the next response in sequence
$first = Atlas::agent('assistant')->chat('1');   // "First response"
$second = Atlas::agent('assistant')->chat('2');  // "Second response"
$third = Atlas::agent('assistant')->chat('3');   // "Third response"
```

### Conditional Responses

Return responses based on input content:

```php
Atlas::fake()
    ->forAgent('assistant')
    ->when(
        fn($agent, $input) => str_contains($input, 'order'),
        AgentResponse::text('I can help with your order')
    )
    ->when(
        fn($agent, $input) => str_contains($input, 'refund'),
        AgentResponse::text('I can process your refund')
    );
```

### Testing Exceptions

Test error handling by throwing exceptions:

```php
use Atlasphp\Atlas\Providers\Exceptions\RateLimitedException;

Atlas::fake()
    ->forAgent('assistant')
    ->throw(new RateLimitedException([], 60));

$this->expectException(RateLimitedException::class);
Atlas::agent('assistant')->chat('Hello');
```

### Streaming Fakes

Create fake streaming responses:

```php
use Atlasphp\Atlas\Testing\Support\StreamEventFactory;

Atlas::fake([
    StreamEventFactory::fromText('Hello, this is streamed!'),
]);

$stream = Atlas::agent('assistant')->chat('Hello', stream: true);

foreach ($stream as $event) {
    // Process events...
}
```

### Assertion Methods

```php
// Assert an agent was called
Atlas::assertCalled('support-agent');

// Assert an agent was called a specific number of times
Atlas::assertCalledTimes('support-agent', 3);

// Assert an agent was NOT called
Atlas::assertNotCalled('billing-agent');

// Assert nothing was called
Atlas::assertNothingCalled();

// Assert with input matching
Atlas::assertCalled('support-agent', fn($recorded) =>
    str_contains($recorded->input, 'order')
);

// Assert with context matching
Atlas::assertSentWithContext('support-agent', fn($context) =>
    $context->metadata['user_id'] === 123
);

// Assert schema was used
Atlas::assertSentWithSchema('analyzer', fn($schema) =>
    $schema->name === 'sentiment'
);
```

### Accessing Recorded Requests

```php
Atlas::fake([AgentResponse::text('Response')]);

Atlas::agent('assistant')->chat('Hello');
Atlas::agent('assistant')->chat('How are you?');

// Get all recorded requests
$recorded = Atlas::recorded();

// Get requests for a specific agent
$assistantRequests = Atlas::recordedFor('assistant');

foreach ($assistantRequests as $request) {
    echo $request->agent;    // Agent key
    echo $request->input;    // User input
    $request->context;       // ExecutionContext
    $request->response;      // AgentResponse
    $request->timestamp;     // When it was called
}
```

### Preventing Stray Requests

Fail tests if an unexpected agent is called:

```php
Atlas::fake()
    ->forAgent('support-agent', AgentResponse::text('OK'))
    ->preventStrayRequests();

// This will pass
Atlas::agent('support-agent')->chat('Hello');

// This will throw an exception - no fake configured for 'other-agent'
Atlas::agent('other-agent')->chat('Hello');  // Throws!
```

### Cleanup

Always restore Atlas after tests:

```php
protected function tearDown(): void
{
    Atlas::unfake();
    parent::tearDown();
}

// Or use the trait
use Atlasphp\Atlas\Testing\Concerns\InteractsWithAtlasFake;

class MyTest extends TestCase
{
    use InteractsWithAtlasFake;  // Automatically unfakes after each test
}
```

## Testing Pipelines

### Testing Pipeline Handlers

```php
class LogAgentExecutionTest extends TestCase
{
    public function test_logs_execution_details(): void
    {
        Log::shouldReceive('info')
            ->twice()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Agent execution');
            });

        $handler = new LogAgentExecution();
        $agent = new TestAgent();

        $handler([
            'agent' => $agent,
            'input' => 'Hello',
            'context' => null,
        ], fn($data) => AgentResponse::text('Hi'));
    }
}
```

### Disabling Pipelines in Tests

```php
public function setUp(): void
{
    parent::setUp();

    // Disable pipelines for isolated testing
    $registry = app(PipelineRegistry::class);
    $registry->setActive('agent.before_execute', false);
    $registry->setActive('agent.after_execute', false);
}
```

## Testing Structured Output

```php
public function test_extracts_structured_data(): void
{
    $schema = new ObjectSchema(
        name: 'sentiment',
        description: 'Sentiment result',
        properties: [
            new StringSchema('sentiment', 'The sentiment'),
        ],
        requiredFields: ['sentiment'],
    );

    // Mock response with structured data
    $response = AgentResponse::structured(['sentiment' => 'positive']);

    $mockManager = Mockery::mock(AtlasManager::class);
    $mockManager->shouldReceive('chat')
        ->withArgs(fn($agent, $input, $messages, $schema) => $schema !== null)
        ->andReturn($response);

    $this->app->instance(AtlasManager::class, $mockManager);

    $result = $this->service->analyzeSentiment('Great product!');

    $this->assertEquals('positive', $result['sentiment']);
}
```

## Best Practices

### 1. Use Factories for Test Data

```php
// AgentResponseFactory.php
class AgentResponseFactory
{
    public static function text(string $text): AgentResponse
    {
        return AgentResponse::text($text);
    }

    public static function withToolCalls(array $calls): AgentResponse
    {
        return AgentResponse::withToolCalls($calls);
    }

    public static function withUsage(int $tokens): AgentResponse
    {
        return AgentResponse::text('Response')
            ->withUsage(['total_tokens' => $tokens]);
    }
}
```

### 2. Test Error Scenarios

```php
public function test_handles_provider_errors(): void
{
    $mockManager = Mockery::mock(AtlasManager::class);
    $mockManager->shouldReceive('chat')
        ->andThrow(new ProviderException('Rate limit exceeded'));

    $this->app->instance(AtlasManager::class, $mockManager);

    $response = $this->postJson('/api/chat', ['message' => 'Hello']);

    $response->assertStatus(503)
        ->assertJson(['error' => 'Service temporarily unavailable']);
}
```

### 3. Separate Unit and Integration Tests

```php
// phpunit.xml
<testsuites>
    <testsuite name="Unit">
        <directory>tests/Unit</directory>
    </testsuite>
    <testsuite name="Integration">
        <directory>tests/Integration</directory>
    </testsuite>
</testsuites>

<groups>
    <exclude>
        <group>requires-api-key</group>
    </exclude>
</groups>
```

## Next Steps

- [Agents](/core-concepts/agents) — Build testable agents
- [Tools](/core-concepts/tools) — Build testable tools
- [Error Handling](/advanced/error-handling) — Test error scenarios
