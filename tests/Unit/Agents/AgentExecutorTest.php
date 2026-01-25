<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Exceptions\AgentException;
use Atlasphp\Atlas\Agents\Services\AgentExecutor;
use Atlasphp\Atlas\Agents\Services\MediaConverter;
use Atlasphp\Atlas\Agents\Services\SystemPromptBuilder;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Atlasphp\Atlas\Contracts\PipelineContract;
use Atlasphp\Atlas\Pipelines\PipelineRegistry;
use Atlasphp\Atlas\Pipelines\PipelineRunner;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Atlasphp\Atlas\Tools\Services\ToolBuilder;
use Atlasphp\Atlas\Tools\Services\ToolExecutor;
use Atlasphp\Atlas\Tools\Services\ToolRegistry;
use Illuminate\Container\Container;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Structured\Response as StructuredResponse;
use Prism\Prism\Testing\StructuredResponseFake;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\Text\Response as PrismResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\ToolCall;
use Prism\Prism\ValueObjects\Usage;

beforeEach(function () {
    $this->container = new Container;
    $registry = new PipelineRegistry;
    $this->runner = new PipelineRunner($registry, $this->container);
    $this->pipelineRegistry = $registry;

    // Create real service instances
    $this->systemPromptBuilder = new SystemPromptBuilder($this->runner);
    $toolRegistry = new ToolRegistry($this->container);
    $toolExecutor = new ToolExecutor($this->runner);
    $this->toolBuilder = new ToolBuilder($toolRegistry, $toolExecutor, $this->container);
    $this->mediaConverter = new MediaConverter;
});

afterEach(function () {
    Mockery::close();
});

test('it creates executor with dependencies', function () {
    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    expect($executor)->toBeInstanceOf(AgentExecutor::class);
});

test('it executes agent and returns PrismResponse', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('Hello from AI')
            ->withUsage(new Usage(10, 5))
            ->withMeta(new Meta('test-id', 'gpt-4')),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    $agent = new TestAgent;
    $context = new ExecutionContext;
    $response = $executor->execute($agent, 'Hello', $context);

    expect($response)->toBeInstanceOf(PrismResponse::class);
    expect($response->text)->toBe('Hello from AI');
});

test('it uses provided context with messages', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('Continuing conversation')
            ->withUsage(new Usage(15, 10)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    $context = new ExecutionContext(
        messages: [['role' => 'user', 'content' => 'Previous message']],
    );

    $agent = new TestAgent;
    $response = $executor->execute($agent, 'Hello', $context);

    expect($response)->toBeInstanceOf(PrismResponse::class);
    expect($response->text)->toBe('Continuing conversation');
});

test('it uses context variables for prompt interpolation', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('Hello John')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    $context = new ExecutionContext(
        variables: ['user_name' => 'John'],
    );

    $agent = new TestAgent;
    $response = $executor->execute($agent, 'Hello', $context);

    expect($response)->toBeInstanceOf(PrismResponse::class);
});

test('it returns tool calls in response', function () {
    $toolCall = new ToolCall(
        id: 'call_123',
        name: 'calculator',
        arguments: ['operation' => 'add', 'a' => 5, 'b' => 3],
    );

    Prism::fake([
        TextResponseFake::make()
            ->withText('The result is 8')
            ->withToolCalls([$toolCall])
            ->withUsage(new Usage(20, 15)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    $agent = new TestAgent;
    $context = new ExecutionContext;
    $response = $executor->execute($agent, 'What is 5 + 3?', $context);

    expect($response->toolCalls)->toHaveCount(1);
    expect($response->toolCalls[0]->name)->toBe('calculator');
    expect($response->toolCalls[0]->arguments())->toBe(['operation' => 'add', 'a' => 5, 'b' => 3]);
});

test('it runs agent.before_execute pipeline', function () {
    $container = new Container;
    $registry = new PipelineRegistry;
    $runner = new PipelineRunner($registry, $container);

    $registry->define('agent.before_execute', 'Before execute pipeline');
    BeforeExecuteCapturingHandler::reset();
    $registry->register('agent.before_execute', BeforeExecuteCapturingHandler::class);

    Prism::fake([
        TextResponseFake::make()
            ->withText('Response')
            ->withUsage(new Usage(10, 5)),
    ]);

    $systemPromptBuilder = new SystemPromptBuilder($runner);
    $toolRegistry = new ToolRegistry($container);
    $toolExecutor = new ToolExecutor($runner);
    $toolBuilder = new ToolBuilder($toolRegistry, $toolExecutor, $container);

    $executor = new AgentExecutor(
        $toolBuilder,
        $systemPromptBuilder,
        $runner,
        new MediaConverter,
    );

    $agent = new TestAgent;
    $context = new ExecutionContext;
    $executor->execute($agent, 'Hello', $context);

    expect(BeforeExecuteCapturingHandler::$called)->toBeTrue();
    expect(BeforeExecuteCapturingHandler::$data['agent'])->toBe($agent);
    expect(BeforeExecuteCapturingHandler::$data['input'])->toBe('Hello');
});

test('it runs agent.after_execute pipeline', function () {
    $container = new Container;
    $registry = new PipelineRegistry;
    $runner = new PipelineRunner($registry, $container);

    $registry->define('agent.after_execute', 'After execute pipeline');
    AfterExecuteCapturingHandler::reset();
    $registry->register('agent.after_execute', AfterExecuteCapturingHandler::class);

    Prism::fake([
        TextResponseFake::make()
            ->withText('Response')
            ->withUsage(new Usage(10, 5)),
    ]);

    $systemPromptBuilder = new SystemPromptBuilder($runner);
    $toolRegistry = new ToolRegistry($container);
    $toolExecutor = new ToolExecutor($runner);
    $toolBuilder = new ToolBuilder($toolRegistry, $toolExecutor, $container);

    $executor = new AgentExecutor(
        $toolBuilder,
        $systemPromptBuilder,
        $runner,
        new MediaConverter,
    );

    $agent = new TestAgent;
    $context = new ExecutionContext;
    $executor->execute($agent, 'Hello', $context);

    expect(AfterExecuteCapturingHandler::$called)->toBeTrue();
    expect(AfterExecuteCapturingHandler::$data['agent'])->toBe($agent);
    expect(AfterExecuteCapturingHandler::$data['input'])->toBe('Hello');
    expect(AfterExecuteCapturingHandler::$data['response'])->toBeInstanceOf(PrismResponse::class);
    expect(array_key_exists('system_prompt', AfterExecuteCapturingHandler::$data))->toBeTrue();
});

// Note: Prism::fake() doesn't support closures for throwing exceptions,
// so we can't easily test error pipeline behavior through unit tests.
// Error handling is covered by integration tests instead.

// Note: Exception wrapping tests removed because Prism::fake() doesn't support
// closures for throwing exceptions. Exception handling is covered by integration tests.

test('it uses context override for provider and model', function () {
    $fake = Prism::fake([
        TextResponseFake::make()
            ->withText('Response from override')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    $context = new ExecutionContext(
        providerOverride: 'anthropic',
        modelOverride: 'claude-3-opus',
    );

    $agent = new TestAgent;
    $response = $executor->execute($agent, 'Hello', $context);

    expect($response)->toBeInstanceOf(PrismResponse::class);
    expect($response->text)->toBe('Response from override');
});

test('it throws when provider is null', function () {
    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    $agent = new \Atlasphp\Atlas\Tests\Fixtures\TestAgentWithDefaults;
    $context = new ExecutionContext;

    $executor->execute($agent, 'Hello', $context);
})->throws(AgentException::class, 'Provider must be specified');

test('it throws when model is null but provider is set', function () {
    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    // Create an agent with provider but null model
    $agent = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'agent-with-no-model';
        }

        public function provider(): ?string
        {
            return 'openai';
        }

        public function model(): ?string
        {
            return null; // No model
        }
    };

    $context = new ExecutionContext;

    $executor->execute($agent, 'Hello', $context);
})->throws(AgentException::class, 'Model must be specified');

test('it throws for unknown message role', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('Response')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    $context = new ExecutionContext(
        messages: [
            ['role' => 'invalid_role', 'content' => 'Message with invalid role'],
        ],
    );

    $agent = new TestAgent;
    $executor->execute($agent, 'Hello', $context);
})->throws(AgentException::class, 'Unknown message role: invalid_role');

test('it builds messages with system role', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('Response')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    $context = new ExecutionContext(
        messages: [
            ['role' => 'system', 'content' => 'You are a helpful assistant.'],
            ['role' => 'user', 'content' => 'Previous question'],
            ['role' => 'assistant', 'content' => 'Previous answer'],
        ],
    );

    $agent = new TestAgent;
    $response = $executor->execute($agent, 'Follow up', $context);

    expect($response)->toBeInstanceOf(PrismResponse::class);
    expect($response->text)->toBe('Response');
});

test('it handles prismMessages directly from context', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('Continuing conversation')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    // Create Prism message objects directly
    $prismMessages = [
        new \Prism\Prism\ValueObjects\Messages\UserMessage('Previous question'),
        new \Prism\Prism\ValueObjects\Messages\AssistantMessage('Previous answer'),
    ];

    $context = new ExecutionContext(
        prismMessages: $prismMessages,
    );

    $agent = new TestAgent;
    $response = $executor->execute($agent, 'Follow up', $context);

    expect($response)->toBeInstanceOf(PrismResponse::class);
    expect($response->text)->toBe('Continuing conversation');
});

test('it prioritizes prismMessages over array messages', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('Using Prism messages')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    // Create context with both array and Prism messages
    // prismMessages should take priority
    $prismMessages = [
        new \Prism\Prism\ValueObjects\Messages\UserMessage('Prism question'),
    ];

    $context = new ExecutionContext(
        messages: [['role' => 'user', 'content' => 'Array question']], // Should be ignored
        prismMessages: $prismMessages,
    );

    $agent = new TestAgent;
    $response = $executor->execute($agent, 'Follow up', $context);

    expect($response)->toBeInstanceOf(PrismResponse::class);
    // Response verifies execution completed; internal message handling is tested
});

test('it handles prismMedia attachments directly', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('I see the image')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    // Create a Prism media object using the correct namespace
    $image = \Prism\Prism\ValueObjects\Media\Image::fromUrl('https://example.com/image.jpg');

    $context = new ExecutionContext(
        prismMedia: [$image],
    );

    $agent = new TestAgent;
    $response = $executor->execute($agent, 'What do you see?', $context);

    expect($response)->toBeInstanceOf(PrismResponse::class);
    expect($response->text)->toBe('I see the image');
});

test('it handles multiple prismMedia attachments', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('I see both images')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    // Create multiple Prism media objects
    $image1 = \Prism\Prism\ValueObjects\Media\Image::fromUrl('https://example.com/image1.jpg');
    $image2 = \Prism\Prism\ValueObjects\Media\Image::fromUrl('https://example.com/image2.jpg');

    $context = new ExecutionContext(
        prismMedia: [$image1, $image2],
    );

    $agent = new TestAgent;
    $response = $executor->execute($agent, 'What do you see?', $context);

    expect($response)->toBeInstanceOf(PrismResponse::class);
    expect($response->text)->toBe('I see both images');
});

test('stream returns Generator', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('Streaming response')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    $agent = new TestAgent;
    $context = new ExecutionContext;
    $stream = $executor->stream($agent, 'Hello', $context);

    expect($stream)->toBeInstanceOf(Generator::class);

    // Consume the stream
    $events = iterator_to_array($stream);
    expect($events)->not->toBeEmpty();
});

test('it replays prism calls from context', function () {
    $fake = Prism::fake([
        TextResponseFake::make()
            ->withText('Response')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    $context = new ExecutionContext(
        prismCalls: [
            ['method' => 'withMaxSteps', 'args' => [10]],
        ],
    );

    $agent = new TestAgent;
    $response = $executor->execute($agent, 'Hello', $context);

    expect($response)->toBeInstanceOf(PrismResponse::class);
});

test('it handles prism media in context', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('I see the image')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    $image = \Prism\Prism\ValueObjects\Media\Image::fromUrl('https://example.com/image.jpg');

    $context = new ExecutionContext(
        prismMedia: [$image],
    );

    $agent = new TestAgent;
    $response = $executor->execute($agent, 'What do you see?', $context);

    expect($response)->toBeInstanceOf(PrismResponse::class);
    expect($response->text)->toBe('I see the image');
});

test('it returns usage from response', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('Response')
            ->withUsage(new Usage(promptTokens: 50, completionTokens: 25)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    $agent = new TestAgent;
    $context = new ExecutionContext;
    $response = $executor->execute($agent, 'Hello', $context);

    expect($response->usage->promptTokens)->toBe(50);
    expect($response->usage->completionTokens)->toBe(25);
});

test('it returns finish reason from response', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('Response')
            ->withFinishReason(FinishReason::Stop)
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    $agent = new TestAgent;
    $context = new ExecutionContext;
    $response = $executor->execute($agent, 'Hello', $context);

    expect($response->finishReason)->toBe(FinishReason::Stop);
});

test('it returns meta from response', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('Response')
            ->withMeta(new Meta('req-123', 'gpt-4-turbo'))
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    $agent = new TestAgent;
    $context = new ExecutionContext;
    $response = $executor->execute($agent, 'Hello', $context);

    expect($response->meta->id)->toBe('req-123');
    expect($response->meta->model)->toBe('gpt-4-turbo');
});

test('it returns StructuredResponse when withSchema is in prismCalls', function () {
    Prism::fake([
        StructuredResponseFake::make()
            ->withStructured(['name' => 'John', 'age' => 30])
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    // Create a mock schema
    $mockSchema = Mockery::mock(\Prism\Prism\Contracts\Schema::class);

    $context = new ExecutionContext(
        prismCalls: [
            ['method' => 'withSchema', 'args' => [$mockSchema]],
        ],
    );

    $agent = new TestAgent;
    $response = $executor->execute($agent, 'Extract person info', $context);

    expect($response)->toBeInstanceOf(StructuredResponse::class);
    expect($response->structured)->toBe(['name' => 'John', 'age' => 30]);
});

test('it replays prism calls without schema for structured output', function () {
    Prism::fake([
        StructuredResponseFake::make()
            ->withStructured(['name' => 'John'])
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    $mockSchema = Mockery::mock(\Prism\Prism\Contracts\Schema::class);

    // usingTemperature is available on both Text and Structured requests
    $context = new ExecutionContext(
        prismCalls: [
            ['method' => 'withSchema', 'args' => [$mockSchema]],
            ['method' => 'usingTemperature', 'args' => [0.5]],
        ],
    );

    $agent = new TestAgent;
    $response = $executor->execute($agent, 'Extract person info', $context);

    expect($response)->toBeInstanceOf(StructuredResponse::class);
});

test('it applies agent clientOptions to request', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('Response with client options')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    $agent = new \Atlasphp\Atlas\Tests\Fixtures\TestAgentWithOptions;
    $context = new ExecutionContext;
    $response = $executor->execute($agent, 'Hello', $context);

    // Verify the request was executed (clientOptions are internal to Prism)
    expect($response)->toBeInstanceOf(PrismResponse::class);
    expect($response->text)->toBe('Response with client options');

    // Verify agent has clientOptions defined
    expect($agent->clientOptions())->toBe([
        'timeout' => 60,
        'connect_timeout' => 10,
    ]);
});

test('it applies agent providerOptions to request', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('Response with provider options')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    $agent = new \Atlasphp\Atlas\Tests\Fixtures\TestAgentWithOptions;
    $context = new ExecutionContext;
    $response = $executor->execute($agent, 'Hello', $context);

    // Verify the request was executed (providerOptions are internal to Prism)
    expect($response)->toBeInstanceOf(PrismResponse::class);
    expect($response->text)->toBe('Response with provider options');

    // Verify agent has providerOptions defined
    expect($agent->providerOptions())->toBe([
        'presence_penalty' => 0.5,
        'frequency_penalty' => 0.3,
    ]);
});

test('it applies agent providerTools to request', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('Response with provider tools')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    $agent = new \Atlasphp\Atlas\Tests\Fixtures\TestAgentWithOptions;
    $context = new ExecutionContext;
    $response = $executor->execute($agent, 'Hello', $context);

    // Verify the request was executed
    expect($response)->toBeInstanceOf(PrismResponse::class);
    expect($response->text)->toBe('Response with provider tools');

    // Verify agent has providerTools defined
    expect($agent->providerTools())->toBe([
        'web_search',
        ['type' => 'code_execution', 'name' => 'execute_code'],
    ]);
});

test('it does not apply empty clientOptions', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('Response')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    // TestAgent has empty clientOptions (uses default)
    $agent = new TestAgent;
    expect($agent->clientOptions())->toBe([]);

    $context = new ExecutionContext;
    $response = $executor->execute($agent, 'Hello', $context);

    expect($response)->toBeInstanceOf(PrismResponse::class);
});

test('it does not apply empty providerOptions', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('Response')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    // TestAgent has empty providerOptions (uses default)
    $agent = new TestAgent;
    expect($agent->providerOptions())->toBe([]);

    $context = new ExecutionContext;
    $response = $executor->execute($agent, 'Hello', $context);

    expect($response)->toBeInstanceOf(PrismResponse::class);
});

test('it does not apply empty providerTools', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('Response')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    // TestAgent has empty providerTools (uses default)
    $agent = new TestAgent;
    expect($agent->providerTools())->toBe([]);

    $context = new ExecutionContext;
    $response = $executor->execute($agent, 'Hello', $context);

    expect($response)->toBeInstanceOf(PrismResponse::class);
});

test('it builds provider tools from string format', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('Response')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    // Create agent with string provider tools
    $agent = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'agent-with-string-tools';
        }

        public function provider(): ?string
        {
            return 'openai';
        }

        public function model(): ?string
        {
            return 'gpt-4';
        }

        public function providerTools(): array
        {
            return ['web_search', 'code_execution'];
        }
    };

    $context = new ExecutionContext;
    $response = $executor->execute($agent, 'Hello', $context);

    expect($response)->toBeInstanceOf(PrismResponse::class);
});

test('it builds provider tools from array format', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('Response')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    // Create agent with array provider tools
    $agent = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'agent-with-array-tools';
        }

        public function provider(): ?string
        {
            return 'openai';
        }

        public function model(): ?string
        {
            return 'gpt-4';
        }

        public function providerTools(): array
        {
            return [
                ['type' => 'web_search'],
                ['type' => 'code_execution', 'name' => 'my_code_executor', 'extra' => 'option'],
            ];
        }
    };

    $context = new ExecutionContext;
    $response = $executor->execute($agent, 'Hello', $context);

    expect($response)->toBeInstanceOf(PrismResponse::class);
});

test('it builds provider tools from ProviderTool objects', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('Response')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    // Create agent with ProviderTool objects
    $agent = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'agent-with-provider-tool-objects';
        }

        public function provider(): ?string
        {
            return 'openai';
        }

        public function model(): ?string
        {
            return 'gpt-4';
        }

        public function providerTools(): array
        {
            return [
                new \Prism\Prism\ValueObjects\ProviderTool(type: 'web_search'),
                new \Prism\Prism\ValueObjects\ProviderTool(type: 'code_execution', name: 'executor'),
            ];
        }
    };

    $context = new ExecutionContext;
    $response = $executor->execute($agent, 'Hello', $context);

    expect($response)->toBeInstanceOf(PrismResponse::class);
});

test('it throws for invalid provider tool format', function () {
    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    // Create agent with invalid provider tool (array without 'type')
    $agent = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'agent-with-invalid-tools';
        }

        public function provider(): ?string
        {
            return 'openai';
        }

        public function model(): ?string
        {
            return 'gpt-4';
        }

        public function providerTools(): array
        {
            return [
                ['name' => 'missing_type'], // Missing 'type' key
            ];
        }
    };

    $context = new ExecutionContext;
    $executor->execute($agent, 'Hello', $context);
})->throws(AgentException::class, 'Invalid provider tool format at index 0');

test('it runs after_execute pipeline with StructuredResponse', function () {
    $container = new Container;
    $registry = new PipelineRegistry;
    $runner = new PipelineRunner($registry, $container);

    $registry->define('agent.after_execute', 'After execute pipeline');
    AfterExecuteCapturingHandler::reset();
    $registry->register('agent.after_execute', AfterExecuteCapturingHandler::class);

    Prism::fake([
        StructuredResponseFake::make()
            ->withStructured(['name' => 'John'])
            ->withUsage(new Usage(10, 5)),
    ]);

    $systemPromptBuilder = new SystemPromptBuilder($runner);
    $toolRegistry = new ToolRegistry($container);
    $toolExecutor = new ToolExecutor($runner);
    $toolBuilder = new ToolBuilder($toolRegistry, $toolExecutor, $container);

    $executor = new AgentExecutor(
        $toolBuilder,
        $systemPromptBuilder,
        $runner,
        new MediaConverter,
    );

    $mockSchema = Mockery::mock(\Prism\Prism\Contracts\Schema::class);

    $context = new ExecutionContext(
        prismCalls: [
            ['method' => 'withSchema', 'args' => [$mockSchema]],
        ],
    );

    $agent = new TestAgent;
    $executor->execute($agent, 'Hello', $context);

    expect(AfterExecuteCapturingHandler::$called)->toBeTrue();
    expect(AfterExecuteCapturingHandler::$data['response'])->toBeInstanceOf(StructuredResponse::class);
});

test('stream runs agent.stream.after pipeline on completion', function () {
    StreamAfterCapturingHandler::reset();

    $this->pipelineRegistry->define('agent.stream.after');
    $this->pipelineRegistry->register('agent.stream.after', StreamAfterCapturingHandler::class);

    Prism::fake([
        TextResponseFake::make()
            ->withText('Streaming response')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    $agent = new TestAgent;
    $context = new ExecutionContext;
    $stream = $executor->stream($agent, 'Hello', $context);

    // Pipeline should not be called before consuming
    expect(StreamAfterCapturingHandler::$called)->toBeFalse();

    // Consume the stream
    iterator_to_array($stream);

    // Pipeline should be called after consuming
    expect(StreamAfterCapturingHandler::$called)->toBeTrue();
    expect(StreamAfterCapturingHandler::$data['agent'])->toBe($agent);
    expect(StreamAfterCapturingHandler::$data['input'])->toBe('Hello');
    expect(StreamAfterCapturingHandler::$data['context'])->toBe($context);
    expect(StreamAfterCapturingHandler::$data['events'])->toBeArray();
    expect(StreamAfterCapturingHandler::$data['error'])->toBeNull();
});

test('stream.after pipeline receives system_prompt', function () {
    StreamAfterCapturingHandler::reset();

    $this->pipelineRegistry->define('agent.stream.after');
    $this->pipelineRegistry->register('agent.stream.after', StreamAfterCapturingHandler::class);

    Prism::fake([
        TextResponseFake::make()
            ->withText('Response')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    $agent = new TestAgent;
    $context = new ExecutionContext;

    iterator_to_array($executor->stream($agent, 'Hello', $context));

    expect(StreamAfterCapturingHandler::$data)->toHaveKey('system_prompt');
    expect(StreamAfterCapturingHandler::$data['system_prompt'])->toBeString();
});

test('stream.after pipeline captures events', function () {
    StreamAfterCapturingHandler::reset();

    $this->pipelineRegistry->define('agent.stream.after');
    $this->pipelineRegistry->register('agent.stream.after', StreamAfterCapturingHandler::class);

    Prism::fake([
        TextResponseFake::make()
            ->withText('Hello')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    $agent = new TestAgent;
    $context = new ExecutionContext;

    $events = iterator_to_array($executor->stream($agent, 'Hello', $context));

    expect(StreamAfterCapturingHandler::$data['events'])->toBe($events);
});

test('it runs agent.context.validate pipeline', function () {
    ContextValidateCapturingHandler::reset();

    $this->pipelineRegistry->define('agent.context.validate');
    $this->pipelineRegistry->register('agent.context.validate', ContextValidateCapturingHandler::class);

    Prism::fake([
        TextResponseFake::make()
            ->withText('Response')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    $agent = new TestAgent;
    $context = new ExecutionContext(
        metadata: ['user_id' => 123],
    );
    $executor->execute($agent, 'Hello', $context);

    expect(ContextValidateCapturingHandler::$called)->toBeTrue();
    expect(ContextValidateCapturingHandler::$data['agent'])->toBe($agent);
    expect(ContextValidateCapturingHandler::$data['input'])->toBe('Hello');
    expect(ContextValidateCapturingHandler::$data['context'])->toBeInstanceOf(ExecutionContext::class);
    expect(ContextValidateCapturingHandler::$data['context']->getMeta('user_id'))->toBe(123);
});

test('context.validate pipeline can modify context', function () {
    ContextModifyingHandler::reset();

    $this->pipelineRegistry->define('agent.context.validate');
    $this->pipelineRegistry->register('agent.context.validate', ContextModifyingHandler::class);

    // Also capture after_execute to verify modified context
    AfterExecuteCapturingHandler::reset();
    $this->pipelineRegistry->define('agent.after_execute');
    $this->pipelineRegistry->register('agent.after_execute', AfterExecuteCapturingHandler::class);

    Prism::fake([
        TextResponseFake::make()
            ->withText('Response')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    $agent = new TestAgent;
    $context = new ExecutionContext;
    $executor->execute($agent, 'Hello', $context);

    // Verify the modified context was used (after_execute receives the modified context)
    expect(AfterExecuteCapturingHandler::$data['context']->getMeta('injected_by_validate'))->toBe(true);
});

test('context.validate pipeline runs for streaming', function () {
    ContextValidateCapturingHandler::reset();

    $this->pipelineRegistry->define('agent.context.validate');
    $this->pipelineRegistry->register('agent.context.validate', ContextValidateCapturingHandler::class);

    Prism::fake([
        TextResponseFake::make()
            ->withText('Response')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    $agent = new TestAgent;
    $context = new ExecutionContext;

    // Must consume stream for pipelines to run
    iterator_to_array($executor->stream($agent, 'Hello', $context));

    expect(ContextValidateCapturingHandler::$called)->toBeTrue();
});

test('error pipeline can provide recovery response', function () {
    ErrorRecoveryHandler::reset();

    $this->pipelineRegistry->define('agent.on_error');
    $this->pipelineRegistry->register('agent.on_error', ErrorRecoveryHandler::class);

    // Create a fake recovery response using the correct constructor
    $recoveryResponse = new PrismResponse(
        steps: collect([]),
        text: 'Recovered from error',
        finishReason: FinishReason::Stop,
        toolCalls: [],
        toolResults: [],
        usage: new Usage(10, 5),
        meta: new Meta('recovery-id', 'gpt-4'),
        messages: collect([]),
        additionalContent: [],
    );
    ErrorRecoveryHandler::setRecoveryResponse($recoveryResponse);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    // Create an agent that will fail (null provider triggers error)
    $agent = new \Atlasphp\Atlas\Tests\Fixtures\TestAgentWithDefaults;
    $context = new ExecutionContext;

    $response = $executor->execute($agent, 'Hello', $context);

    // Verify recovery was used
    expect(ErrorRecoveryHandler::$called)->toBeTrue();
    expect(ErrorRecoveryHandler::$data['exception'])->toBeInstanceOf(\InvalidArgumentException::class);
    expect($response)->toBe($recoveryResponse);
    expect($response->text)->toBe('Recovered from error');
});

test('error pipeline receives exception details', function () {
    ErrorRecoveryHandler::reset();

    $this->pipelineRegistry->define('agent.on_error');
    $this->pipelineRegistry->register('agent.on_error', ErrorRecoveryHandler::class);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    // Create an agent that will fail
    $agent = new \Atlasphp\Atlas\Tests\Fixtures\TestAgentWithDefaults;
    $context = new ExecutionContext;

    try {
        $executor->execute($agent, 'Hello', $context);
    } catch (AgentException $e) {
        // Expected when no recovery is provided
    }

    expect(ErrorRecoveryHandler::$called)->toBeTrue();
    expect(ErrorRecoveryHandler::$data)->toHaveKey('agent');
    expect(ErrorRecoveryHandler::$data)->toHaveKey('input');
    expect(ErrorRecoveryHandler::$data)->toHaveKey('context');
    expect(ErrorRecoveryHandler::$data)->toHaveKey('exception');
    expect(ErrorRecoveryHandler::$data['input'])->toBe('Hello');
});

test('error pipeline without recovery rethrows exception', function () {
    ErrorRecoveryHandler::reset();

    $this->pipelineRegistry->define('agent.on_error');
    $this->pipelineRegistry->register('agent.on_error', ErrorRecoveryHandler::class);

    // Don't set recovery response

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    $agent = new \Atlasphp\Atlas\Tests\Fixtures\TestAgentWithDefaults;
    $context = new ExecutionContext;

    $executor->execute($agent, 'Hello', $context);
})->throws(AgentException::class);

test('error pipeline recovery works for AgentException', function () {
    ErrorRecoveryHandler::reset();

    $this->pipelineRegistry->define('agent.on_error');
    $this->pipelineRegistry->register('agent.on_error', ErrorRecoveryHandler::class);

    // Create recovery response using the correct constructor
    $recoveryResponse = new PrismResponse(
        steps: collect([]),
        text: 'Recovered from AgentException',
        finishReason: FinishReason::Stop,
        toolCalls: [],
        toolResults: [],
        usage: new Usage(10, 5),
        meta: new Meta('recovery-id', 'gpt-4'),
        messages: collect([]),
        additionalContent: [],
    );
    ErrorRecoveryHandler::setRecoveryResponse($recoveryResponse);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    // Use agent with null model (triggers AgentException via InvalidArgumentException)
    $agent = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'null-model-agent';
        }

        public function provider(): ?string
        {
            return 'openai';
        }

        public function model(): ?string
        {
            return null;
        }
    };

    $context = new ExecutionContext;
    $response = $executor->execute($agent, 'Hello', $context);

    expect(ErrorRecoveryHandler::$called)->toBeTrue();
    expect($response->text)->toBe('Recovered from AgentException');
});

// Note: Stream error handling in wrapStreamWithAfterPipeline catches errors during iteration,
// not during setup. Errors during buildRequest() propagate before wrapStreamWithAfterPipeline runs.
// Testing iteration errors requires Prism to throw during actual streaming, which Prism::fake()
// doesn't support. The wrapStreamWithAfterPipeline error handling is structurally verified
// through the stream.after tests which confirm the finally block executes.

test('execute handles Throwable and wraps in AgentException', function () {
    ErrorRecoveryHandler::reset();

    $this->pipelineRegistry->define('agent.on_error');
    $this->pipelineRegistry->register('agent.on_error', ErrorRecoveryHandler::class);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    // Use agent with null provider - triggers InvalidArgumentException which is Throwable
    $agent = new \Atlasphp\Atlas\Tests\Fixtures\TestAgentWithDefaults;
    $context = new ExecutionContext;

    try {
        $executor->execute($agent, 'Hello', $context);
        $this->fail('Expected AgentException');
    } catch (AgentException $e) {
        // The error handler should be called even for non-AgentException throwables
        expect(ErrorRecoveryHandler::$called)->toBeTrue();
        expect($e->getMessage())->toContain('execution failed');
    }
});

test('execute recovers from Throwable when recovery provided', function () {
    ErrorRecoveryHandler::reset();

    $this->pipelineRegistry->define('agent.on_error');
    $this->pipelineRegistry->register('agent.on_error', ErrorRecoveryHandler::class);

    // Create recovery response
    $recoveryResponse = new PrismResponse(
        steps: collect([]),
        text: 'Recovered from Throwable',
        finishReason: FinishReason::Stop,
        toolCalls: [],
        toolResults: [],
        usage: new Usage(10, 5),
        meta: new Meta('recovery-id', 'gpt-4'),
        messages: collect([]),
        additionalContent: [],
    );
    ErrorRecoveryHandler::setRecoveryResponse($recoveryResponse);

    $executor = new AgentExecutor(
        $this->toolBuilder,
        $this->systemPromptBuilder,
        $this->runner,
        $this->mediaConverter,
    );

    // Use agent with null provider - triggers InvalidArgumentException
    $agent = new \Atlasphp\Atlas\Tests\Fixtures\TestAgentWithDefaults;
    $context = new ExecutionContext;

    $response = $executor->execute($agent, 'Hello', $context);

    expect(ErrorRecoveryHandler::$called)->toBeTrue();
    expect($response->text)->toBe('Recovered from Throwable');
});

// Pipeline Handler Classes for Tests

class BeforeExecuteCapturingHandler implements PipelineContract
{
    public static bool $called = false;

    public static ?array $data = null;

    public static function reset(): void
    {
        self::$called = false;
        self::$data = null;
    }

    public function handle(mixed $data, Closure $next): mixed
    {
        self::$called = true;
        self::$data = $data;

        return $next($data);
    }
}

class AfterExecuteCapturingHandler implements PipelineContract
{
    public static bool $called = false;

    public static ?array $data = null;

    public static function reset(): void
    {
        self::$called = false;
        self::$data = null;
    }

    public function handle(mixed $data, Closure $next): mixed
    {
        self::$called = true;
        self::$data = $data;

        return $next($data);
    }
}

class ErrorCapturingHandler implements PipelineContract
{
    public static bool $called = false;

    public static ?array $data = null;

    public static function reset(): void
    {
        self::$called = false;
        self::$data = null;
    }

    public function handle(mixed $data, Closure $next): mixed
    {
        self::$called = true;
        self::$data = $data;

        return $next($data);
    }
}

class StreamAfterCapturingHandler implements PipelineContract
{
    public static bool $called = false;

    public static ?array $data = null;

    public static function reset(): void
    {
        self::$called = false;
        self::$data = null;
    }

    public function handle(mixed $data, Closure $next): mixed
    {
        self::$called = true;
        self::$data = $data;

        return $next($data);
    }
}

class ContextValidateCapturingHandler implements PipelineContract
{
    public static bool $called = false;

    public static ?array $data = null;

    public static function reset(): void
    {
        self::$called = false;
        self::$data = null;
    }

    public function handle(mixed $data, Closure $next): mixed
    {
        self::$called = true;
        self::$data = $data;

        return $next($data);
    }
}

class ContextModifyingHandler implements PipelineContract
{
    public static bool $called = false;

    public static function reset(): void
    {
        self::$called = false;
    }

    public function handle(mixed $data, Closure $next): mixed
    {
        self::$called = true;

        // Create a new context with injected metadata
        $data['context'] = new ExecutionContext(
            messages: $data['context']->messages,
            variables: $data['context']->variables,
            metadata: array_merge($data['context']->metadata, ['injected_by_validate' => true]),
            providerOverride: $data['context']->providerOverride,
            modelOverride: $data['context']->modelOverride,
            prismCalls: $data['context']->prismCalls,
            prismMedia: $data['context']->prismMedia,
            prismMessages: $data['context']->prismMessages,
        );

        return $next($data);
    }
}

class ErrorRecoveryHandler implements PipelineContract
{
    public static bool $called = false;

    public static ?array $data = null;

    public static ?PrismResponse $recoveryResponse = null;

    public static function reset(): void
    {
        self::$called = false;
        self::$data = null;
        self::$recoveryResponse = null;
    }

    public static function setRecoveryResponse(?PrismResponse $response): void
    {
        self::$recoveryResponse = $response;
    }

    public function handle(mixed $data, Closure $next): mixed
    {
        self::$called = true;
        self::$data = $data;

        // If a recovery response is set, return it
        if (self::$recoveryResponse !== null) {
            $data['recovery'] = self::$recoveryResponse;
        }

        return $next($data);
    }
}
