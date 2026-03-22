<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Middleware\AgentContext;
use Atlasphp\Atlas\Persistence\Concerns\HasConversations;
use Atlasphp\Atlas\Persistence\Concerns\HasMemory;
use Atlasphp\Atlas\Persistence\Memory\MemoryConfig;
use Atlasphp\Atlas\Persistence\Memory\MemoryContext;
use Atlasphp\Atlas\Persistence\Middleware\WireMemory;
use Atlasphp\Atlas\Persistence\Models\Memory;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Support\VariableRegistry;

function wireMemoryTextRequest(): TextRequest
{
    return new TextRequest(
        model: 'gpt-5',
        instructions: null,
        message: 'Hello',
        messageMedia: [],
        messages: [],
        maxTokens: null,
        temperature: null,
        schema: null,
        tools: [],
        providerTools: [],
        providerOptions: [],
    );
}

function wireMemoryAgentWithMemory(MemoryConfig $config): Agent
{
    return new class($config) extends Agent
    {
        use HasMemory;

        public function __construct(protected MemoryConfig $memoryConfig) {}

        public function key(): string
        {
            return 'test-agent';
        }

        public function name(): string
        {
            return 'Test Agent';
        }

        public function description(): string
        {
            return 'Test agent with memory';
        }

        public function provider(): Provider|string|null
        {
            return Provider::OpenAI;
        }

        public function model(): ?string
        {
            return 'gpt-5';
        }

        public function memory(): MemoryConfig
        {
            return $this->memoryConfig;
        }
    };
}

function wireMemoryAgentWithoutMemory(): Agent
{
    return new class extends Agent
    {
        public function key(): string
        {
            return 'plain-agent';
        }

        public function name(): string
        {
            return 'Plain Agent';
        }

        public function description(): string
        {
            return 'Agent without memory';
        }

        public function provider(): Provider|string|null
        {
            return Provider::OpenAI;
        }

        public function model(): ?string
        {
            return 'gpt-5';
        }
    };
}

it('skips agents without HasMemory trait', function () {
    $middleware = app(WireMemory::class);
    $agent = wireMemoryAgentWithoutMemory();

    $context = new AgentContext(
        request: wireMemoryTextRequest(),
        agent: $agent,
    );

    $called = false;
    $middleware->handle($context, function ($ctx) use (&$called) {
        $called = true;

        return 'pass-through';
    });

    expect($called)->toBeTrue()
        ->and($context->tools)->toBe([]);
});

it('skips when agent is null', function () {
    $middleware = app(WireMemory::class);

    $context = new AgentContext(
        request: wireMemoryTextRequest(),
        agent: null,
    );

    $result = $middleware->handle($context, fn () => 'pass-through');

    expect($result)->toBe('pass-through');
});

it('configures MemoryContext with owner and agent key', function () {
    $middleware = app(WireMemory::class);
    $memoryContext = app(MemoryContext::class);

    $config = MemoryConfig::make()->withTools();
    $agent = wireMemoryAgentWithMemory($config);

    $context = new AgentContext(
        request: wireMemoryTextRequest(),
        agent: $agent,
    );

    $capturedOwner = null;
    $capturedAgent = null;

    $middleware->handle($context, function () use ($memoryContext, &$capturedOwner, &$capturedAgent) {
        $capturedOwner = $memoryContext->owner();
        $capturedAgent = $memoryContext->agentKey();

        return 'done';
    });

    // Owner is null because agent doesn't use HasConversations
    expect($capturedOwner)->toBeNull()
        ->and($capturedAgent)->toBe('test-agent');
});

it('appends memory tools based on config', function () {
    $middleware = app(WireMemory::class);

    $config = MemoryConfig::make()->withSearch()->withRemember();
    $agent = wireMemoryAgentWithMemory($config);

    $context = new AgentContext(
        request: wireMemoryTextRequest(),
        agent: $agent,
    );

    $middleware->handle($context, function ($ctx) {
        return 'done';
    });

    expect($context->tools)->toHaveCount(2);

    $toolNames = array_map(fn ($t) => $t->name(), $context->tools);
    expect($toolNames)->toContain('search_memory')
        ->and($toolNames)->toContain('remember_memory')
        ->and($toolNames)->not->toContain('recall_memory');
});

it('appends all tools when withTools is used', function () {
    $middleware = app(WireMemory::class);

    $config = MemoryConfig::make()->withTools();
    $agent = wireMemoryAgentWithMemory($config);

    $context = new AgentContext(
        request: wireMemoryTextRequest(),
        agent: $agent,
    );

    $middleware->handle($context, function ($ctx) {
        return 'done';
    });

    expect($context->tools)->toHaveCount(3);

    $toolNames = array_map(fn ($t) => $t->name(), $context->tools);
    expect($toolNames)->toContain('search_memory')
        ->and($toolNames)->toContain('recall_memory')
        ->and($toolNames)->toContain('remember_memory');
});

it('does not append tools when none configured', function () {
    $middleware = app(WireMemory::class);

    $config = MemoryConfig::make();
    $agent = wireMemoryAgentWithMemory($config);

    $context = new AgentContext(
        request: wireMemoryTextRequest(),
        agent: $agent,
    );

    $middleware->handle($context, function ($ctx) {
        return 'done';
    });

    expect($context->tools)->toBe([]);
});

it('registers variable documents in VariableRegistry', function () {
    $middleware = app(WireMemory::class);
    $variables = app(VariableRegistry::class);

    // Create a memory document to be loaded
    Memory::factory()->create([
        'type' => 'soul',
        'content' => 'I am a helpful assistant',
        'memoryable_type' => null,
        'memoryable_id' => null,
        'agent' => null,
    ]);

    $config = MemoryConfig::make()->variables(['soul']);
    $agent = wireMemoryAgentWithMemory($config);

    $context = new AgentContext(
        request: wireMemoryTextRequest(),
        agent: $agent,
    );

    $capturedValue = null;

    $middleware->handle($context, function () use ($variables, &$capturedValue) {
        $resolved = $variables->resolve();
        $capturedValue = $resolved['SOUL'] ?? null;

        return 'done';
    });

    expect($capturedValue)->toBe('I am a helpful assistant');
});

it('cleans up variable documents after execution', function () {
    $middleware = app(WireMemory::class);
    $variables = app(VariableRegistry::class);

    Memory::factory()->create([
        'type' => 'soul',
        'content' => 'test',
        'memoryable_type' => null,
        'memoryable_id' => null,
        'agent' => null,
    ]);

    $config = MemoryConfig::make()->variables(['soul']);
    $agent = wireMemoryAgentWithMemory($config);

    $context = new AgentContext(
        request: wireMemoryTextRequest(),
        agent: $agent,
    );

    $middleware->handle($context, fn () => 'done');

    // After execution, variable should be cleaned up
    $resolved = $variables->resolve();
    expect($resolved)->not->toHaveKey('SOUL');
});

it('resets MemoryContext after execution', function () {
    $middleware = app(WireMemory::class);
    $memoryContext = app(MemoryContext::class);

    $config = MemoryConfig::make();
    $agent = wireMemoryAgentWithMemory($config);

    $context = new AgentContext(
        request: wireMemoryTextRequest(),
        agent: $agent,
    );

    $middleware->handle($context, fn () => 'done');

    expect($memoryContext->isConfigured())->toBeFalse();
});

it('does not write memory data to context meta', function () {
    $middleware = app(WireMemory::class);

    $config = MemoryConfig::make()->withTools();
    $agent = wireMemoryAgentWithMemory($config);

    $context = new AgentContext(
        request: wireMemoryTextRequest(),
        agent: $agent,
        meta: ['existing_key' => 'value'],
    );

    $middleware->handle($context, function ($ctx) {
        // Verify no memory keys were added to meta
        expect($ctx->meta)->not->toHaveKey('memory_owner_type')
            ->and($ctx->meta)->not->toHaveKey('memory_owner_id')
            ->and($ctx->meta)->not->toHaveKey('memory_agent')
            ->and($ctx->meta)->toHaveKey('existing_key');

        return 'done';
    });
});
