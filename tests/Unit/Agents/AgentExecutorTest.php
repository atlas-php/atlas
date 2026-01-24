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

test('it combines prismMedia with currentAttachments', function () {
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

    // Create a Prism media object using the correct namespace
    $image = \Prism\Prism\ValueObjects\Media\Image::fromUrl('https://example.com/image1.jpg');

    $context = new ExecutionContext(
        prismMedia: [$image],
        currentAttachments: [
            ['type' => 'image', 'source' => 'url', 'data' => 'https://example.com/image2.jpg'],
        ],
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

test('it handles current attachments in context', function () {
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

    $context = new ExecutionContext(
        currentAttachments: [
            ['type' => 'image', 'source' => 'url', 'data' => 'https://example.com/image.jpg'],
        ],
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
