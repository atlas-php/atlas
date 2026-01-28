<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Atlasphp\Atlas\Agents\Support\AgentContext;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Agents\Support\AgentStreamResponse;
use Atlasphp\Atlas\Agents\Support\PendingAgentRequest;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Illuminate\Support\Collection;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Text\Response as PrismResponse;
use Prism\Prism\ValueObjects\Media\Audio;
use Prism\Prism\ValueObjects\Media\Document;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Media\Video;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

function makeMockPrismResponse(string $text): PrismResponse
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

function makeMockAgentResponse(string $text, $agent, string $input, AgentContext $context): AgentResponse
{
    return new AgentResponse(
        response: makeMockPrismResponse($text),
        agent: $agent,
        input: $input,
        systemPrompt: null,
        context: $context,
    );
}

beforeEach(function () {
    $this->agent = new TestAgent;
    $this->resolver = Mockery::mock(AgentResolver::class);
    $this->executor = Mockery::mock(AgentExecutorContract::class);

    $this->resolver->shouldReceive('resolve')
        ->with($this->agent)
        ->andReturn($this->agent)
        ->byDefault();

    $this->request = new PendingAgentRequest(
        $this->resolver,
        $this->executor,
        $this->agent,
    );
});

afterEach(function () {
    Mockery::close();
});

// === withMessages ===

test('withMessages sets conversation history immutably', function () {
    $messages = [
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi there!'],
    ];

    $request2 = $this->request->withMessages($messages);

    expect($request2)->not->toBe($this->request);
    expect($request2)->toBeInstanceOf(PendingAgentRequest::class);
});

test('withMessages includes messages in context', function () {
    $messages = [
        ['role' => 'user', 'content' => 'Previous message'],
    ];

    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->withMessages($messages)
        ->chat('New message');

    expect($capturedContext->messages)->toBe($messages);
});

test('withMessages accepts Prism message objects', function () {
    $prismMessages = [
        new \Prism\Prism\ValueObjects\Messages\UserMessage('Previous message'),
        new \Prism\Prism\ValueObjects\Messages\AssistantMessage('Previous response'),
    ];

    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->withMessages($prismMessages)
        ->chat('New message');

    expect($capturedContext->prismMessages)->toBe($prismMessages);
    expect($capturedContext->messages)->toBe([]);
    expect($capturedContext->hasPrismMessages())->toBeTrue();
});

test('withMessages with array format sets messages and clears prismMessages', function () {
    $messages = [
        ['role' => 'user', 'content' => 'Hello'],
    ];

    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->withMessages($messages)
        ->chat('New message');

    expect($capturedContext->messages)->toBe($messages);
    expect($capturedContext->prismMessages)->toBe([]);
    expect($capturedContext->hasPrismMessages())->toBeFalse();
});

test('withMessages with Prism message objects includes SystemMessage', function () {
    $prismMessages = [
        new \Prism\Prism\ValueObjects\Messages\SystemMessage('You are a helpful assistant.'),
        new \Prism\Prism\ValueObjects\Messages\UserMessage('Hello'),
    ];

    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->withMessages($prismMessages)
        ->chat('Follow up');

    expect($capturedContext->prismMessages)->toHaveCount(2);
    expect($capturedContext->prismMessages[0])->toBeInstanceOf(\Prism\Prism\ValueObjects\Messages\SystemMessage::class);
    expect($capturedContext->prismMessages[1])->toBeInstanceOf(\Prism\Prism\ValueObjects\Messages\UserMessage::class);
});

// === withVariables ===

test('withVariables sets variables immutably', function () {
    $request2 = $this->request->withVariables(['name' => 'John']);

    expect($request2)->not->toBe($this->request);
});

test('withVariables includes variables in context', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->withVariables(['name' => 'John', 'role' => 'admin'])
        ->chat('Hello');

    expect($capturedContext->variables)->toBe(['name' => 'John', 'role' => 'admin']);
});

test('withVariables replaces previous variables', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->withVariables(['name' => 'John'])
        ->withVariables(['role' => 'admin'])
        ->chat('Hello');

    expect($capturedContext->variables)->toBe(['role' => 'admin']);
});

test('mergeVariables accumulates across multiple calls', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->mergeVariables(['name' => 'John'])
        ->mergeVariables(['role' => 'admin'])
        ->chat('Hello');

    expect($capturedContext->variables)->toBe(['name' => 'John', 'role' => 'admin']);
});

test('mergeVariables later calls override same keys', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->mergeVariables(['name' => 'John', 'role' => 'user'])
        ->mergeVariables(['role' => 'admin'])
        ->chat('Hello');

    expect($capturedContext->variables)->toBe(['name' => 'John', 'role' => 'admin']);
});

test('clearVariables removes all variables', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->withVariables(['name' => 'John', 'role' => 'admin'])
        ->clearVariables()
        ->chat('Hello');

    expect($capturedContext->variables)->toBe([]);
});

// === withMetadata ===

test('withMetadata sets metadata immutably', function () {
    $request2 = $this->request->withMetadata(['request_id' => '123']);

    expect($request2)->not->toBe($this->request);
});

test('withMetadata includes metadata in context', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->withMetadata(['request_id' => '123', 'user_id' => 456])
        ->chat('Hello');

    expect($capturedContext->metadata)->toBe(['request_id' => '123', 'user_id' => 456]);
});

test('withMetadata replaces previous metadata', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->withMetadata(['request_id' => '123'])
        ->withMetadata(['user_id' => 456])
        ->chat('Hello');

    expect($capturedContext->metadata)->toBe(['user_id' => 456]);
});

test('mergeMetadata accumulates across multiple calls', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->mergeMetadata(['request_id' => '123'])
        ->mergeMetadata(['user_id' => 456])
        ->chat('Hello');

    expect($capturedContext->metadata)->toBe(['request_id' => '123', 'user_id' => 456]);
});

test('mergeMetadata later calls override same keys', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->mergeMetadata(['request_id' => '123', 'trace_id' => 'abc'])
        ->mergeMetadata(['trace_id' => 'xyz'])
        ->chat('Hello');

    expect($capturedContext->metadata)->toBe(['request_id' => '123', 'trace_id' => 'xyz']);
});

test('clearMetadata removes all metadata', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->withMetadata(['request_id' => '123', 'user_id' => 456])
        ->clearMetadata()
        ->chat('Hello');

    expect($capturedContext->metadata)->toBe([]);
});

// === withProvider / withModel ===

test('withProvider sets provider override immutably', function () {
    $request2 = $this->request->withProvider('anthropic');

    expect($request2)->not->toBe($this->request);
});

test('withProvider includes provider override in context', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->withProvider('anthropic')
        ->chat('Hello');

    expect($capturedContext->providerOverride)->toBe('anthropic');
});

test('withProvider can set both provider and model', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->withProvider('anthropic', 'claude-3-opus')
        ->chat('Hello');

    expect($capturedContext->providerOverride)->toBe('anthropic');
    expect($capturedContext->modelOverride)->toBe('claude-3-opus');
});

test('withModel sets model override immutably', function () {
    $request2 = $this->request->withModel('gpt-4-turbo');

    expect($request2)->not->toBe($this->request);
});

test('withModel includes model override in context', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->withModel('gpt-4-turbo')
        ->chat('Hello');

    expect($capturedContext->modelOverride)->toBe('gpt-4-turbo');
});

// === __call (Prism method forwarding) ===

test('__call captures prism method calls immutably', function () {
    $request2 = $this->request->usingTemperature(0.7);

    expect($request2)->not->toBe($this->request);
});

test('__call captures multiple prism method calls', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->usingTemperature(0.7)
        ->usingMaxTokens(1000)
        ->withClientRetry(3)
        ->chat('Hello');

    expect($capturedContext->prismCalls)->toHaveCount(3);
    expect($capturedContext->prismCalls[0])->toBe(['method' => 'usingTemperature', 'args' => [0.7]]);
    expect($capturedContext->prismCalls[1])->toBe(['method' => 'usingMaxTokens', 'args' => [1000]]);
    expect($capturedContext->prismCalls[2])->toBe(['method' => 'withClientRetry', 'args' => [3]]);
});

// === withMedia (Prism objects - builder style) ===

test('withMedia adds single Prism media object', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $image = Image::fromUrl('https://example.com/image.png');

    $this->request
        ->withMedia($image)
        ->chat('What is this?');

    expect($capturedContext->prismMedia)->toHaveCount(1);
    expect($capturedContext->prismMedia[0])->toBe($image);
});

test('withMedia adds multiple Prism media objects', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $image = Image::fromUrl('https://example.com/image.png');
    $document = Document::fromUrl('https://example.com/doc.pdf');

    $this->request
        ->withMedia([$image, $document])
        ->chat('Analyze these');

    expect($capturedContext->prismMedia)->toHaveCount(2);
});

test('withMedia is immutable', function () {
    $image = Image::fromUrl('https://example.com/image.png');

    $request2 = $this->request->withMedia($image);

    expect($request2)->not->toBe($this->request);
});

// === chat ===

test('chat resolves agent and executes', function () {
    $this->resolver->shouldReceive('resolve')
        ->once()
        ->with($this->agent)
        ->andReturn($this->agent);

    $this->executor->shouldReceive('execute')
        ->once()
        ->with($this->agent, 'Hello', Mockery::type(AgentContext::class))
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Hi there!', $agent, $input, $context);
        });

    $response = $this->request->chat('Hello');

    expect($response)->toBeInstanceOf(AgentResponse::class);
    expect($response->text)->toBe('Hi there!');
});

test('chat passes input correctly', function () {
    $capturedInput = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedInput) {
            $capturedInput = $input;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request->chat('Test input message');

    expect($capturedInput)->toBe('Test input message');
});

test('chat accepts inline attachments (Prism-style)', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $image = Image::fromUrl('https://example.com/image.png');

    $this->request->chat('Describe this image', [$image]);

    expect($capturedContext->prismMedia)->toHaveCount(1);
    expect($capturedContext->prismMedia[0])->toBe($image);
});

test('chat merges builder attachments with inline attachments', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $builderImage = Image::fromUrl('https://example.com/builder-image.png');
    $inlineImage = Image::fromUrl('https://example.com/inline-image.png');

    $this->request
        ->withMedia($builderImage)
        ->chat('Compare these images', [$inlineImage]);

    expect($capturedContext->prismMedia)->toHaveCount(2);
    expect($capturedContext->prismMedia[0])->toBe($builderImage);
    expect($capturedContext->prismMedia[1])->toBe($inlineImage);
});

test('chat accepts multiple inline attachments', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $image = Image::fromUrl('https://example.com/image.png');
    $document = Document::fromUrl('https://example.com/doc.pdf');

    $this->request->chat('Analyze these', [$image, $document]);

    expect($capturedContext->prismMedia)->toHaveCount(2);
    expect($capturedContext->prismMedia[0])->toBeInstanceOf(Image::class);
    expect($capturedContext->prismMedia[1])->toBeInstanceOf(Document::class);
});

test('chat accepts all media types inline', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $image = Image::fromUrl('https://example.com/image.png');
    $document = Document::fromUrl('https://example.com/doc.pdf');
    $audio = Audio::fromUrl('https://example.com/audio.mp3');
    $video = Video::fromUrl('https://example.com/video.mp4');

    $this->request->chat('Process all these', [$image, $document, $audio, $video]);

    expect($capturedContext->prismMedia)->toHaveCount(4);
    expect($capturedContext->prismMedia[0])->toBeInstanceOf(Image::class);
    expect($capturedContext->prismMedia[1])->toBeInstanceOf(Document::class);
    expect($capturedContext->prismMedia[2])->toBeInstanceOf(Audio::class);
    expect($capturedContext->prismMedia[3])->toBeInstanceOf(Video::class);
});

// === stream ===

test('stream resolves agent and returns AgentStreamResponse', function () {
    $this->resolver->shouldReceive('resolve')
        ->once()
        ->with($this->agent)
        ->andReturn($this->agent);

    $this->executor->shouldReceive('stream')
        ->once()
        ->with($this->agent, 'Hello', Mockery::type(AgentContext::class))
        ->andReturnUsing(function ($agent, $input, $context) {
            return new AgentStreamResponse(
                stream: (function () {
                    yield 'chunk1';
                    yield 'chunk2';
                })(),
                agent: $agent,
                input: $input,
                systemPrompt: null,
                context: $context,
            );
        });

    $result = $this->request->stream('Hello');

    expect($result)->toBeInstanceOf(AgentStreamResponse::class);

    $chunks = iterator_to_array($result);
    expect($chunks)->toBe(['chunk1', 'chunk2']);
});

test('stream accepts inline attachments (Prism-style)', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('stream')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return new AgentStreamResponse(
                stream: (function () {
                    yield 'chunk';
                })(),
                agent: $agent,
                input: $input,
                systemPrompt: null,
                context: $context,
            );
        });

    $image = Image::fromUrl('https://example.com/image.png');

    iterator_to_array($this->request->stream('Describe this', [$image]));

    expect($capturedContext->prismMedia)->toHaveCount(1);
    expect($capturedContext->prismMedia[0])->toBe($image);
});

// === Fluent chaining ===

test('all methods can be chained fluently', function () {
    $this->executor->shouldReceive('execute')
        ->once()
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $image = Image::fromUrl('https://example.com/image.png');

    $response = $this->request
        ->withMessages([['role' => 'user', 'content' => 'Previous']])
        ->withVariables(['name' => 'John'])
        ->withMetadata(['request_id' => '123'])
        ->withProvider('anthropic', 'claude-3-opus')
        ->usingTemperature(0.7)
        ->chat('Hello', [$image]);

    expect($response)->toBeInstanceOf(AgentResponse::class);
});

test('chaining preserves all configuration', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $image = Image::fromUrl('https://example.com/image.png');

    $this->request
        ->withMessages([['role' => 'user', 'content' => 'Hi']])
        ->withVariables(['name' => 'John'])
        ->withMetadata(['id' => '123'])
        ->withProvider('anthropic')
        ->withModel('claude-3-opus')
        ->usingTemperature(0.7)
        ->chat('Hello', [$image]);

    expect($capturedContext->messages)->toBe([['role' => 'user', 'content' => 'Hi']]);
    expect($capturedContext->variables)->toBe(['name' => 'John']);
    expect($capturedContext->metadata)->toBe(['id' => '123']);
    expect($capturedContext->providerOverride)->toBe('anthropic');
    expect($capturedContext->modelOverride)->toBe('claude-3-opus');
    expect($capturedContext->prismMedia)->toHaveCount(1);
    expect($capturedContext->prismMedia[0])->toBeInstanceOf(Image::class);
    expect($capturedContext->prismCalls)->toHaveCount(1);
});

// === withTools Tests ===

test('withTools adds tools immutably', function () {
    $request2 = $this->request->withTools(['App\\Tools\\MyTool']);

    expect($request2)->not->toBe($this->request);
    expect($request2)->toBeInstanceOf(PendingAgentRequest::class);
});

test('withTools includes tools in context', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->withTools(['App\\Tools\\MyTool'])
        ->chat('Hello');

    expect($capturedContext->tools)->toHaveCount(1);
    expect($capturedContext->tools[0])->toBe('App\\Tools\\MyTool');
});

test('withTools replaces with chained calls', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->withTools(['App\\Tools\\ToolA'])
        ->withTools(['App\\Tools\\ToolB'])
        ->chat('Hello');

    expect($capturedContext->tools)->toHaveCount(1);
    expect($capturedContext->tools[0])->toBe('App\\Tools\\ToolB');
});

test('mergeTools accumulates with chained calls', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->withTools(['App\\Tools\\ToolA'])
        ->mergeTools(['App\\Tools\\ToolB'])
        ->chat('Hello');

    expect($capturedContext->tools)->toHaveCount(2);
    expect($capturedContext->tools[0])->toBe('App\\Tools\\ToolA');
    expect($capturedContext->tools[1])->toBe('App\\Tools\\ToolB');
});

test('withTools works with multiple tools in single call', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->withTools(['App\\Tools\\ToolA', 'App\\Tools\\ToolB'])
        ->chat('Hello');

    expect($capturedContext->tools)->toHaveCount(2);
});

test('withTools can be combined with withMcpTools', function () {
    $mockMcpTool = Mockery::mock(\Prism\Prism\Tool::class);

    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->withTools(['App\\Tools\\MyTool'])
        ->withMcpTools([$mockMcpTool])
        ->chat('Hello');

    expect($capturedContext->tools)->toHaveCount(1);
    expect($capturedContext->mcpTools)->toHaveCount(1);
});

// === withMcpTools Tests ===

test('withMcpTools adds tools immutably', function () {
    $mockTool = Mockery::mock(\Prism\Prism\Tool::class);

    $request2 = $this->request->withMcpTools([$mockTool]);

    expect($request2)->not->toBe($this->request);
    expect($request2)->toBeInstanceOf(PendingAgentRequest::class);
});

test('withMcpTools includes tools in context', function () {
    $mockTool = Mockery::mock(\Prism\Prism\Tool::class);

    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->withMcpTools([$mockTool])
        ->chat('Hello');

    expect($capturedContext->mcpTools)->toHaveCount(1);
    expect($capturedContext->mcpTools[0])->toBe($mockTool);
});

test('withMcpTools replaces with chained calls', function () {
    $mockTool1 = Mockery::mock(\Prism\Prism\Tool::class);
    $mockTool2 = Mockery::mock(\Prism\Prism\Tool::class);

    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->withMcpTools([$mockTool1])
        ->withMcpTools([$mockTool2])
        ->chat('Hello');

    expect($capturedContext->mcpTools)->toHaveCount(1);
    expect($capturedContext->mcpTools[0])->toBe($mockTool2);
});

test('mergeMcpTools accumulates with chained calls', function () {
    $mockTool1 = Mockery::mock(\Prism\Prism\Tool::class);
    $mockTool2 = Mockery::mock(\Prism\Prism\Tool::class);

    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->withMcpTools([$mockTool1])
        ->mergeMcpTools([$mockTool2])
        ->chat('Hello');

    expect($capturedContext->mcpTools)->toHaveCount(2);
    expect($capturedContext->mcpTools[0])->toBe($mockTool1);
    expect($capturedContext->mcpTools[1])->toBe($mockTool2);
});

test('withMcpTools works with multiple tools in single call', function () {
    $mockTool1 = Mockery::mock(\Prism\Prism\Tool::class);
    $mockTool2 = Mockery::mock(\Prism\Prism\Tool::class);

    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->withMcpTools([$mockTool1, $mockTool2])
        ->chat('Hello');

    expect($capturedContext->mcpTools)->toHaveCount(2);
});

test('withMcpTools can be combined with other methods', function () {
    $mockTool = Mockery::mock(\Prism\Prism\Tool::class);

    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->withVariables(['name' => 'John'])
        ->withMcpTools([$mockTool])
        ->withMetadata(['id' => '123'])
        ->chat('Hello');

    expect($capturedContext->mcpTools)->toHaveCount(1);
    expect($capturedContext->variables)->toBe(['name' => 'John']);
    expect($capturedContext->metadata)->toBe(['id' => '123']);
});

// === withContext Tests ===

test('withContext applies context immutably', function () {
    $context = new AgentContext(
        messages: [['role' => 'user', 'content' => 'Hello']],
        variables: ['name' => 'John'],
    );

    $request2 = $this->request->withContext($context);

    expect($request2)->not->toBe($this->request);
    expect($request2)->toBeInstanceOf(PendingAgentRequest::class);
});

test('withContext applies all serializable properties', function () {
    $context = new AgentContext(
        messages: [['role' => 'user', 'content' => 'Previous']],
        variables: ['name' => 'John', 'role' => 'admin'],
        metadata: ['request_id' => '123', 'user_id' => 456],
        providerOverride: 'anthropic',
        modelOverride: 'claude-3-opus',
        prismCalls: [['method' => 'usingTemperature', 'args' => [0.7]]],
        tools: ['App\\Tools\\MyTool'],
    );

    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $ctx) use (&$capturedContext) {
            $capturedContext = $ctx;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->withContext($context)
        ->chat('New message');

    expect($capturedContext->messages)->toBe([['role' => 'user', 'content' => 'Previous']]);
    expect($capturedContext->variables)->toBe(['name' => 'John', 'role' => 'admin']);
    expect($capturedContext->metadata)->toBe(['request_id' => '123', 'user_id' => 456]);
    expect($capturedContext->providerOverride)->toBe('anthropic');
    expect($capturedContext->modelOverride)->toBe('claude-3-opus');
    expect($capturedContext->prismCalls)->toBe([['method' => 'usingTemperature', 'args' => [0.7]]]);
    expect($capturedContext->tools)->toBe(['App\\Tools\\MyTool']);
});

test('withContext applies prismMessages from context', function () {
    $prismMessages = [
        new \Prism\Prism\ValueObjects\Messages\UserMessage('Previous message'),
    ];

    $context = new AgentContext(
        prismMessages: $prismMessages,
    );

    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $ctx) use (&$capturedContext) {
            $capturedContext = $ctx;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->withContext($context)
        ->chat('New message');

    expect($capturedContext->prismMessages)->toBe($prismMessages);
});

test('withContext applies mcpTools from context', function () {
    $mockTool = Mockery::mock(\Prism\Prism\Tool::class);

    $context = new AgentContext(
        mcpTools: [$mockTool],
    );

    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $ctx) use (&$capturedContext) {
            $capturedContext = $ctx;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->withContext($context)
        ->chat('Hello');

    expect($capturedContext->mcpTools)->toHaveCount(1);
    expect($capturedContext->mcpTools[0])->toBe($mockTool);
});

test('withContext applies prismMedia from context', function () {
    $image = Image::fromUrl('https://example.com/image.png');

    $context = new AgentContext(
        prismMedia: [$image],
    );

    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $ctx) use (&$capturedContext) {
            $capturedContext = $ctx;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->withContext($context)
        ->chat('Describe this');

    expect($capturedContext->prismMedia)->toHaveCount(1);
    expect($capturedContext->prismMedia[0])->toBe($image);
});

test('withContext can be chained with additional methods', function () {
    $context = new AgentContext(
        variables: ['name' => 'John'],
        metadata: ['request_id' => '123'],
    );

    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $ctx) use (&$capturedContext) {
            $capturedContext = $ctx;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $image = Image::fromUrl('https://example.com/new-image.png');

    $this->request
        ->withContext($context)
        ->withMedia($image)
        ->mergeVariables(['role' => 'admin'])
        ->chat('Hello');

    // Original context values preserved
    expect($capturedContext->variables)->toBe(['name' => 'John', 'role' => 'admin']);
    expect($capturedContext->metadata)->toBe(['request_id' => '123']);
    // New media added after context
    expect($capturedContext->prismMedia)->toHaveCount(1);
});

test('withContext works with fromArray for queue round-trip', function () {
    // Simulate queue serialization round-trip
    $originalContext = new AgentContext(
        messages: [['role' => 'user', 'content' => 'Hello']],
        variables: ['user_id' => 123],
        metadata: ['task_id' => 'abc'],
        providerOverride: 'anthropic',
        modelOverride: 'claude-3-opus',
        prismCalls: [['method' => 'withMaxSteps', 'args' => [5]]],
        tools: ['App\\Tools\\SearchTool'],
    );

    // Serialize and deserialize (simulating queue transport)
    $serialized = $originalContext->toArray();
    $restoredContext = AgentContext::fromArray($serialized);

    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $ctx) use (&$capturedContext) {
            $capturedContext = $ctx;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->withContext($restoredContext)
        ->chat('Continue conversation');

    expect($capturedContext->messages)->toBe([['role' => 'user', 'content' => 'Hello']]);
    expect($capturedContext->variables)->toBe(['user_id' => 123]);
    expect($capturedContext->metadata)->toBe(['task_id' => 'abc']);
    expect($capturedContext->providerOverride)->toBe('anthropic');
    expect($capturedContext->modelOverride)->toBe('claude-3-opus');
    expect($capturedContext->prismCalls)->toBe([['method' => 'withMaxSteps', 'args' => [5]]]);
    expect($capturedContext->tools)->toBe(['App\\Tools\\SearchTool']);
});

// === middleware Tests ===

test('middleware adds handlers immutably', function () {
    $request2 = $this->request->middleware([
        'agent.before_execute' => 'App\\Middleware\\TestMiddleware',
    ]);

    expect($request2)->not->toBe($this->request);
    expect($request2)->toBeInstanceOf(PendingAgentRequest::class);
});

test('middleware includes handlers in context', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->middleware([
            'agent.before_execute' => 'App\\Middleware\\TestMiddleware',
        ])
        ->chat('Hello');

    expect($capturedContext->middleware)->toHaveKey('agent.before_execute');
    expect($capturedContext->middleware['agent.before_execute'])->toHaveCount(1);
    expect($capturedContext->middleware['agent.before_execute'][0]['handler'])->toBe('App\\Middleware\\TestMiddleware');
    expect($capturedContext->middleware['agent.before_execute'][0]['priority'])->toBe(0);
});

test('middleware accepts single handler per event', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->middleware([
            'agent.before_execute' => 'App\\Middleware\\FirstMiddleware',
            'agent.after_execute' => 'App\\Middleware\\SecondMiddleware',
        ])
        ->chat('Hello');

    expect($capturedContext->middleware)->toHaveKey('agent.before_execute');
    expect($capturedContext->middleware)->toHaveKey('agent.after_execute');
    expect($capturedContext->middleware['agent.before_execute'][0]['handler'])->toBe('App\\Middleware\\FirstMiddleware');
    expect($capturedContext->middleware['agent.after_execute'][0]['handler'])->toBe('App\\Middleware\\SecondMiddleware');
});

test('middleware accepts array of handlers per event', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->middleware([
            'agent.before_execute' => [
                'App\\Middleware\\FirstMiddleware',
                'App\\Middleware\\SecondMiddleware',
            ],
        ])
        ->chat('Hello');

    expect($capturedContext->middleware['agent.before_execute'])->toHaveCount(2);
    expect($capturedContext->middleware['agent.before_execute'][0]['handler'])->toBe('App\\Middleware\\FirstMiddleware');
    expect($capturedContext->middleware['agent.before_execute'][1]['handler'])->toBe('App\\Middleware\\SecondMiddleware');
});

test('middleware accepts handler instances', function () {
    $mockHandler = Mockery::mock(\Atlasphp\Atlas\Contracts\PipelineContract::class);

    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->middleware([
            'agent.after_execute' => $mockHandler,
        ])
        ->chat('Hello');

    expect($capturedContext->middleware['agent.after_execute'][0]['handler'])->toBe($mockHandler);
});

test('middleware accumulates across multiple calls', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->middleware(['agent.before_execute' => 'App\\Middleware\\AuthMiddleware'])
        ->middleware(['agent.after_execute' => 'App\\Middleware\\LogMiddleware'])
        ->chat('Hello');

    expect($capturedContext->middleware)->toHaveKey('agent.before_execute');
    expect($capturedContext->middleware)->toHaveKey('agent.after_execute');
    expect($capturedContext->middleware['agent.before_execute'][0]['handler'])->toBe('App\\Middleware\\AuthMiddleware');
    expect($capturedContext->middleware['agent.after_execute'][0]['handler'])->toBe('App\\Middleware\\LogMiddleware');
});

test('middleware accumulates handlers for same event across calls', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->middleware(['agent.before_execute' => 'App\\Middleware\\FirstMiddleware'])
        ->middleware(['agent.before_execute' => 'App\\Middleware\\SecondMiddleware'])
        ->chat('Hello');

    expect($capturedContext->middleware['agent.before_execute'])->toHaveCount(2);
    expect($capturedContext->middleware['agent.before_execute'][0]['handler'])->toBe('App\\Middleware\\FirstMiddleware');
    expect($capturedContext->middleware['agent.before_execute'][1]['handler'])->toBe('App\\Middleware\\SecondMiddleware');
});

test('withoutMiddleware removes all middleware', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->middleware(['agent.before_execute' => 'App\\Middleware\\TestMiddleware'])
        ->middleware(['agent.after_execute' => 'App\\Middleware\\LogMiddleware'])
        ->withoutMiddleware()
        ->chat('Hello');

    expect($capturedContext->middleware)->toBe([]);
});

test('withoutMiddleware is immutable', function () {
    $request2 = $this->request
        ->middleware(['agent.before_execute' => 'App\\Middleware\\TestMiddleware'])
        ->withoutMiddleware();

    expect($request2)->not->toBe($this->request);
    expect($request2)->toBeInstanceOf(PendingAgentRequest::class);
});

test('middleware can be combined with other methods', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturnUsing(function ($agent, $input, $context) {
            return makeMockAgentResponse('Response', $agent, $input, $context);
        });

    $this->request
        ->withVariables(['name' => 'John'])
        ->middleware(['agent.before_execute' => 'App\\Middleware\\AuthMiddleware'])
        ->withMetadata(['id' => '123'])
        ->chat('Hello');

    expect($capturedContext->variables)->toBe(['name' => 'John']);
    expect($capturedContext->metadata)->toBe(['id' => '123']);
    expect($capturedContext->middleware)->toHaveKey('agent.before_execute');
});
