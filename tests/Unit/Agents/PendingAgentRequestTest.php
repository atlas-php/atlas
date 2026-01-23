<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Atlasphp\Atlas\Agents\Support\PendingAgentRequest;
use Atlasphp\Atlas\Streaming\StreamResponse;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Prism\Prism\Contracts\Schema;

beforeEach(function () {
    $this->agentResolver = Mockery::mock(AgentResolver::class);
    $this->agentExecutor = Mockery::mock(AgentExecutorContract::class);
    $this->agent = 'test-agent';

    $this->request = new PendingAgentRequest(
        $this->agentResolver,
        $this->agentExecutor,
        $this->agent,
    );
});

afterEach(function () {
    Mockery::close();
});

test('withMessages returns new instance with messages', function () {
    $messages = [['role' => 'user', 'content' => 'Previous']];

    $result = $this->request->withMessages($messages);

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingAgentRequest::class);
});

test('withVariables returns new instance with variables', function () {
    $variables = ['user_name' => 'John'];

    $result = $this->request->withVariables($variables);

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingAgentRequest::class);
});

test('withMetadata returns new instance with metadata', function () {
    $metadata = ['session_id' => 'abc123'];

    $result = $this->request->withMetadata($metadata);

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingAgentRequest::class);
});

test('withRetry returns new instance with retry config', function () {
    $result = $this->request->withRetry(3, 1000);

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingAgentRequest::class);
});

test('withSchema returns new instance with schema', function () {
    $schema = Mockery::mock(Schema::class);

    $result = $this->request->withSchema($schema);

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingAgentRequest::class);
});

test('chat resolves agent and executes', function () {
    $agent = new TestAgent;
    $response = AgentResponse::text('Hello');

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->with('test-agent')
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->with($agent, 'Hello', null, null, null, null)
        ->andReturn($response);

    $result = $this->request->chat('Hello');

    expect($result)->toBe($response);
});

test('chat passes messages to context', function () {
    $agent = new TestAgent;
    $messages = [['role' => 'user', 'content' => 'Previous']];
    $response = AgentResponse::text('Hello');

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($a, $input, $context, $schema, $retry, $structuredMode) use ($agent, $messages) {
            return $a === $agent
                && $input === 'Hello'
                && $context instanceof ExecutionContext
                && $context->messages === $messages
                && $schema === null
                && $retry === null;
        })
        ->andReturn($response);

    $this->request->withMessages($messages)->chat('Hello');
});

test('chat passes variables to context', function () {
    $agent = new TestAgent;
    $variables = ['user_name' => 'John'];
    $response = AgentResponse::text('Hello John');

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($a, $input, $context, $schema, $retry, $structuredMode) use ($agent, $variables) {
            return $a === $agent
                && $input === 'Hello'
                && $context instanceof ExecutionContext
                && $context->variables === $variables
                && $schema === null
                && $retry === null;
        })
        ->andReturn($response);

    $this->request->withVariables($variables)->chat('Hello');
});

test('chat passes metadata to context', function () {
    $agent = new TestAgent;
    $metadata = ['session_id' => 'abc123'];
    $response = AgentResponse::text('Hello');

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($a, $input, $context, $schema, $retry, $structuredMode) use ($agent, $metadata) {
            return $a === $agent
                && $input === 'Hello'
                && $context instanceof ExecutionContext
                && $context->metadata === $metadata
                && $schema === null
                && $retry === null;
        })
        ->andReturn($response);

    $this->request->withMetadata($metadata)->chat('Hello');
});

test('chat passes retry config to executor', function () {
    $agent = new TestAgent;
    $response = AgentResponse::text('Hello');

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($a, $input, $context, $schema, $retry, $structuredMode) use ($agent) {
            return $a === $agent
                && $input === 'Hello'
                && $retry !== null
                && $retry[0] === 3
                && $retry[1] === 1000;
        })
        ->andReturn($response);

    $this->request->withRetry(3, 1000)->chat('Hello');
});

test('chat with stream returns StreamResponse', function () {
    $agent = new TestAgent;
    $streamResponse = Mockery::mock(StreamResponse::class);

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('stream')
        ->once()
        ->with($agent, 'Hello', null, null)
        ->andReturn($streamResponse);

    $result = $this->request->chat('Hello', stream: true);

    expect($result)->toBe($streamResponse);
});

test('chat with schema returns structured response', function () {
    $agent = new TestAgent;
    $schema = Mockery::mock(Schema::class);
    $response = AgentResponse::structured(['name' => 'John']);

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->with($agent, 'Hello', null, $schema, null, null)
        ->andReturn($response);

    $result = $this->request->withSchema($schema)->chat('Hello');

    expect($result)->toBe($response);
});

test('withSchema accepts SchemaBuilder and auto-builds', function () {
    $agent = new TestAgent;
    $response = AgentResponse::structured(['name' => 'John']);

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($a, $input, $context, $schema, $retry, $structuredMode) use ($agent) {
            // Verify the SchemaBuilder was auto-built into an ObjectSchema
            return $a === $agent
                && $input === 'Hello'
                && $schema instanceof \Prism\Prism\Schema\ObjectSchema
                && $schema->name === 'test'
                && count($schema->properties) === 1;
        })
        ->andReturn($response);

    $builder = \Atlasphp\Atlas\Schema\Schema::object('test', 'Test schema')
        ->string('name', 'Name');

    $result = $this->request->withSchema($builder)->chat('Hello');

    expect($result)->toBe($response);
});

test('chat throws exception when streaming with schema', function () {
    $agent = new TestAgent;
    $schema = Mockery::mock(Schema::class);

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->andReturn($agent);

    expect(fn () => $this->request->withSchema($schema)->chat('Hello', stream: true))
        ->toThrow(
            \InvalidArgumentException::class,
            'Streaming does not support structured output (schema). Use stream: false for structured responses.'
        );
});

test('chaining preserves all config', function () {
    $agent = new TestAgent;
    $messages = [['role' => 'user', 'content' => 'Previous']];
    $variables = ['user_name' => 'John'];
    $metadata = ['session_id' => 'abc123'];
    $response = AgentResponse::text('Hello');

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($a, $input, $context, $schema, $retry, $structuredMode) use ($agent, $messages, $variables, $metadata) {
            return $a === $agent
                && $input === 'Hello'
                && $context instanceof ExecutionContext
                && $context->messages === $messages
                && $context->variables === $variables
                && $context->metadata === $metadata
                && $retry !== null
                && $retry[0] === 3;
        })
        ->andReturn($response);

    $this->request
        ->withMessages($messages)
        ->withVariables($variables)
        ->withMetadata($metadata)
        ->withRetry(3, 1000)
        ->chat('Hello');
});

test('chaining with schema preserves all config', function () {
    $agent = new TestAgent;
    $messages = [['role' => 'user', 'content' => 'Previous']];
    $variables = ['user_name' => 'John'];
    $metadata = ['session_id' => 'abc123'];
    $schema = Mockery::mock(Schema::class);
    $response = AgentResponse::structured(['name' => 'John']);

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($a, $input, $context, $actualSchema, $retry) use ($agent, $messages, $variables, $metadata, $schema) {
            return $a === $agent
                && $input === 'Hello'
                && $context instanceof ExecutionContext
                && $context->messages === $messages
                && $context->variables === $variables
                && $context->metadata === $metadata
                && $actualSchema === $schema
                && $retry !== null
                && $retry[0] === 3;
        })
        ->andReturn($response);

    $this->request
        ->withMessages($messages)
        ->withVariables($variables)
        ->withMetadata($metadata)
        ->withSchema($schema)
        ->withRetry(3, 1000)
        ->chat('Hello');
});

test('chat accepts agent instance', function () {
    $agent = new TestAgent;
    $response = AgentResponse::text('Hello');

    $request = new PendingAgentRequest(
        $this->agentResolver,
        $this->agentExecutor,
        $agent,
    );

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->with($agent)
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->with($agent, 'Hello', null, null, null, null)
        ->andReturn($response);

    $result = $request->chat('Hello');

    expect($result)->toBe($response);
});

test('chat accepts agent class string', function () {
    $agent = new TestAgent;
    $response = AgentResponse::text('Hello');

    $request = new PendingAgentRequest(
        $this->agentResolver,
        $this->agentExecutor,
        TestAgent::class,
    );

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->with(TestAgent::class)
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->with($agent, 'Hello', null, null, null, null)
        ->andReturn($response);

    $result = $request->chat('Hello');

    expect($result)->toBe($response);
});

test('withProvider returns new instance with provider', function () {
    $result = $this->request->withProvider('anthropic');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingAgentRequest::class);
});

test('withModel returns new instance with model', function () {
    $result = $this->request->withModel('gpt-4');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingAgentRequest::class);
});

test('chat passes provider override to context', function () {
    $agent = new TestAgent;
    $response = AgentResponse::text('Hello');

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($a, $input, $context, $schema, $retry, $structuredMode) use ($agent) {
            return $a === $agent
                && $input === 'Hello'
                && $context instanceof ExecutionContext
                && $context->providerOverride === 'anthropic'
                && $schema === null
                && $retry === null;
        })
        ->andReturn($response);

    $this->request->withProvider('anthropic')->chat('Hello');
});

test('chat passes model override to context', function () {
    $agent = new TestAgent;
    $response = AgentResponse::text('Hello');

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($a, $input, $context, $schema, $retry, $structuredMode) use ($agent) {
            return $a === $agent
                && $input === 'Hello'
                && $context instanceof ExecutionContext
                && $context->modelOverride === 'claude-3-opus'
                && $schema === null
                && $retry === null;
        })
        ->andReturn($response);

    $this->request->withModel('claude-3-opus')->chat('Hello');
});

test('chat passes both provider and model overrides to context', function () {
    $agent = new TestAgent;
    $response = AgentResponse::text('Hello');

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($a, $input, $context, $schema, $retry, $structuredMode) use ($agent) {
            return $a === $agent
                && $input === 'Hello'
                && $context instanceof ExecutionContext
                && $context->providerOverride === 'anthropic'
                && $context->modelOverride === 'claude-3-opus'
                && $schema === null
                && $retry === null;
        })
        ->andReturn($response);

    $this->request
        ->withProvider('anthropic')
        ->withModel('claude-3-opus')
        ->chat('Hello');
});

// ===========================================
// MEDIA SUPPORT TESTS
// ===========================================

test('withImage returns new instance with image attachment', function () {
    $result = $this->request->withImage('https://example.com/image.jpg');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingAgentRequest::class);
});

test('withDocument returns new instance with document attachment', function () {
    $result = $this->request->withDocument('https://example.com/doc.pdf');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingAgentRequest::class);
});

test('withAudio returns new instance with audio attachment', function () {
    $result = $this->request->withAudio('https://example.com/audio.mp3');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingAgentRequest::class);
});

test('withVideo returns new instance with video attachment', function () {
    $result = $this->request->withVideo('https://example.com/video.mp4');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingAgentRequest::class);
});

test('chat passes current attachments to context', function () {
    $agent = new TestAgent;
    $response = AgentResponse::text('I can see the image');

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($a, $input, $context, $schema, $retry, $structuredMode) use ($agent) {
            return $a === $agent
                && $input === 'What do you see?'
                && $context instanceof ExecutionContext
                && $context->hasCurrentAttachments()
                && count($context->currentAttachments) === 1
                && $context->currentAttachments[0]['type'] === 'image'
                && $context->currentAttachments[0]['source'] === 'url'
                && $context->currentAttachments[0]['data'] === 'https://example.com/image.jpg';
        })
        ->andReturn($response);

    $this->request
        ->withImage('https://example.com/image.jpg')
        ->chat('What do you see?');
});

test('chat passes multiple attachments to context', function () {
    $agent = new TestAgent;
    $response = AgentResponse::text('I can see multiple items');

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($a, $input, $context, $schema, $retry, $structuredMode) use ($agent) {
            return $a === $agent
                && $input === 'Describe what you see'
                && $context instanceof ExecutionContext
                && $context->hasCurrentAttachments()
                && count($context->currentAttachments) === 2
                && $context->currentAttachments[0]['type'] === 'image'
                && $context->currentAttachments[1]['type'] === 'document';
        })
        ->andReturn($response);

    $this->request
        ->withImage('https://example.com/image.jpg')
        ->withDocument('https://example.com/doc.pdf')
        ->chat('Describe what you see');
});

test('chat with attachments and messages creates context', function () {
    $agent = new TestAgent;
    $messages = [['role' => 'user', 'content' => 'Hello']];
    $response = AgentResponse::text('I see the image');

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($a, $input, $context, $schema, $retry, $structuredMode) use ($agent, $messages) {
            return $a === $agent
                && $input === 'Now look at this'
                && $context instanceof ExecutionContext
                && $context->messages === $messages
                && $context->hasCurrentAttachments()
                && count($context->currentAttachments) === 1;
        })
        ->andReturn($response);

    $this->request
        ->withMessages($messages)
        ->withImage('https://example.com/image.jpg')
        ->chat('Now look at this');
});

test('chat with only attachments creates context', function () {
    $agent = new TestAgent;
    $response = AgentResponse::text('I see the image');

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($a, $input, $context, $schema, $retry, $structuredMode) use ($agent) {
            // Context should be created because there are attachments
            return $a === $agent
                && $input === 'What is this?'
                && $context instanceof ExecutionContext
                && $context->hasCurrentAttachments();
        })
        ->andReturn($response);

    $this->request
        ->withImage('https://example.com/image.jpg')
        ->chat('What is this?');
});

// ===========================================
// WHENPROVIDER TESTS
// ===========================================

test('whenProvider returns new instance with callback', function () {
    $result = $this->request->whenProvider('anthropic', fn ($r) => $r);

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingAgentRequest::class);
});

test('whenProvider applies callback when provider matches via agent', function () {
    // TestAgent returns 'openai' as its provider
    $agent = new TestAgent;
    $response = AgentResponse::text('Hello');

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($a, $input, $context, $schema, $retry, $structuredMode) use ($agent) {
            return $a === $agent
                && $input === 'Hello'
                && $context instanceof ExecutionContext
                && $context->hasProviderOptions()
                && $context->providerOptions['presence_penalty'] === 0.5;
        })
        ->andReturn($response);

    // TestAgent's provider is 'openai', so the openai callback should apply
    $this->request
        ->whenProvider('openai', fn ($r) => $r->withProviderOptions(['presence_penalty' => 0.5]))
        ->chat('Hello');
});

test('whenProvider does not apply callback when provider does not match', function () {
    // TestAgent returns 'openai' as its provider
    $agent = new TestAgent;
    $response = AgentResponse::text('Hello');

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($a, $input, $context, $schema, $retry, $structuredMode) use ($agent) {
            // TestAgent provider is 'openai', so 'anthropic' callback should NOT apply
            return $a === $agent
                && $input === 'Hello'
                && ($context === null || ! $context->hasProviderOptions());
        })
        ->andReturn($response);

    $this->request
        ->whenProvider('anthropic', fn ($r) => $r->withProviderOptions(['cacheType' => 'ephemeral']))
        ->chat('Hello');
});

test('whenProvider uses provider override for matching', function () {
    // TestAgent has 'openai' but we override to 'anthropic'
    $agent = new TestAgent;
    $response = AgentResponse::text('Hello');

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($a, $input, $context, $schema, $retry, $structuredMode) use ($agent) {
            return $a === $agent
                && $input === 'Hello'
                && $context instanceof ExecutionContext
                && $context->providerOverride === 'anthropic'
                && $context->hasProviderOptions()
                && $context->providerOptions['cacheType'] === 'ephemeral';
        })
        ->andReturn($response);

    // withProvider sets anthropic, so anthropic callback should apply
    $this->request
        ->withProvider('anthropic')
        ->whenProvider('anthropic', fn ($r) => $r->withProviderOptions(['cacheType' => 'ephemeral']))
        ->chat('Hello');
});

test('whenProvider chains multiple provider configs', function () {
    // TestAgent returns 'openai' as its provider
    $agent = new TestAgent;
    $response = AgentResponse::text('Hello');

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($a, $input, $context, $schema, $retry, $structuredMode) use ($agent) {
            // Only openai callback should apply (TestAgent's provider)
            return $a === $agent
                && $input === 'Hello'
                && $context instanceof ExecutionContext
                && $context->hasProviderOptions()
                && $context->providerOptions['presence_penalty'] === 0.5
                && ! isset($context->providerOptions['cacheType']);
        })
        ->andReturn($response);

    $this->request
        ->whenProvider('anthropic', fn ($r) => $r->withProviderOptions(['cacheType' => 'ephemeral']))
        ->whenProvider('openai', fn ($r) => $r->withProviderOptions(['presence_penalty' => 0.5]))
        ->chat('Hello');
});

test('whenProvider with stream applies callback when provider matches', function () {
    // TestAgent returns 'openai' as its provider
    $agent = new TestAgent;
    $streamResponse = Mockery::mock(StreamResponse::class);

    $this->agentResolver
        ->shouldReceive('resolve')
        ->once()
        ->andReturn($agent);

    $this->agentExecutor
        ->shouldReceive('stream')
        ->once()
        ->withArgs(function ($a, $input, $context, $retry) use ($agent) {
            return $a === $agent
                && $input === 'Hello'
                && $context instanceof ExecutionContext
                && $context->hasProviderOptions()
                && $context->providerOptions['presence_penalty'] === 0.5;
        })
        ->andReturn($streamResponse);

    // TestAgent's provider is 'openai', so openai callback should apply
    $this->request
        ->whenProvider('openai', fn ($r) => $r->withProviderOptions(['presence_penalty' => 0.5]))
        ->chat('Hello', stream: true);
});
