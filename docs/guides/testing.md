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
use App\Tools\LookupOrderTool;
use Atlasphp\Atlas\Tools\Support\ToolContext;
use Atlasphp\Atlas\Tools\Support\ToolResult;
use PHPUnit\Framework\TestCase;

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

Create a fake for testing:

```php
class FakeAtlasManager
{
    private array $responses = [];
    private array $calls = [];

    public function fake(string $agent, string|AgentResponse $response): self
    {
        $this->responses[$agent] = is_string($response)
            ? AgentResponse::text($response)
            : $response;
        return $this;
    }

    public function chat(string $agent, string $input, ...$args): AgentResponse
    {
        $this->calls[] = compact('agent', 'input', 'args');

        return $this->responses[$agent]
            ?? AgentResponse::text("Fake response for {$agent}");
    }

    public function assertCalled(string $agent, ?string $input = null): void
    {
        $found = collect($this->calls)
            ->filter(fn($call) => $call['agent'] === $agent)
            ->when($input, fn($calls) =>
                $calls->filter(fn($call) => str_contains($call['input'], $input))
            );

        PHPUnit::assertTrue($found->isNotEmpty(), "Agent {$agent} was not called");
    }
}
```

Usage:

```php
public function test_service_calls_correct_agent(): void
{
    $fake = new FakeAtlasManager();
    $fake->fake('support-agent', 'I can help with that!');

    $this->app->instance(AtlasManager::class, $fake);

    $service = app(CustomerService::class);
    $service->handleQuery('Help with my order');

    $fake->assertCalled('support-agent', 'order');
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

- [Creating Agents](/guides/creating-agents) — Build testable agents
- [Creating Tools](/guides/creating-tools) — Build testable tools
- [Error Handling](/advanced/error-handling) — Test error scenarios
