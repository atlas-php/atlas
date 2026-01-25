<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Enums\MediaSource;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
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
        ->andReturn(makeMockPrismResponse('Response'));

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
        ->andReturn(makeMockPrismResponse('Response'));

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
        ->andReturn(makeMockPrismResponse('Response'));

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
        ->andReturn(makeMockPrismResponse('Response'));

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
        ->andReturn(makeMockPrismResponse('Response'));

    $this->request
        ->withVariables(['name' => 'John', 'role' => 'admin'])
        ->chat('Hello');

    expect($capturedContext->variables)->toBe(['name' => 'John', 'role' => 'admin']);
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
        ->andReturn(makeMockPrismResponse('Response'));

    $this->request
        ->withMetadata(['request_id' => '123', 'user_id' => 456])
        ->chat('Hello');

    expect($capturedContext->metadata)->toBe(['request_id' => '123', 'user_id' => 456]);
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
        ->andReturn(makeMockPrismResponse('Response'));

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
        ->andReturn(makeMockPrismResponse('Response'));

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
        ->andReturn(makeMockPrismResponse('Response'));

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
        ->andReturn(makeMockPrismResponse('Response'));

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

// === withImage ===

test('withImage adds single image attachment as Prism Image object', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturn(makeMockPrismResponse('Response'));

    $this->request
        ->withImage('https://example.com/image.png')
        ->chat('What is in this image?');

    expect($capturedContext->prismMedia)->toHaveCount(1);
    expect($capturedContext->prismMedia[0])->toBeInstanceOf(Image::class);
});

test('withImage adds multiple image attachments as Prism Image objects', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturn(makeMockPrismResponse('Response'));

    $this->request
        ->withImage([
            'https://example.com/image1.png',
            'https://example.com/image2.png',
        ])
        ->chat('Compare these images');

    expect($capturedContext->prismMedia)->toHaveCount(2);
    expect($capturedContext->prismMedia[0])->toBeInstanceOf(Image::class);
    expect($capturedContext->prismMedia[1])->toBeInstanceOf(Image::class);
});

test('withImage supports base64 source', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturn(makeMockPrismResponse('Response'));

    $this->request
        ->withImage('base64data...', MediaSource::Base64, 'image/png')
        ->chat('What is this?');

    expect($capturedContext->prismMedia)->toHaveCount(1);
    expect($capturedContext->prismMedia[0])->toBeInstanceOf(Image::class);
});

test('withImage supports file path source', function () {
    // Create a temporary image file for testing
    $tempFile = tempnam(sys_get_temp_dir(), 'test_image_');
    file_put_contents($tempFile, 'fake image data');

    try {
        $capturedContext = null;
        $this->executor->shouldReceive('execute')
            ->once()
            ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
                $capturedContext = $context;

                return true;
            })
            ->andReturn(makeMockPrismResponse('Response'));

        $this->request
            ->withImage($tempFile, MediaSource::LocalPath)
            ->chat('What is this?');

        expect($capturedContext->prismMedia)->toHaveCount(1);
        expect($capturedContext->prismMedia[0])->toBeInstanceOf(Image::class);
    } finally {
        @unlink($tempFile);
    }
});

test('withImage supports storage path source with disk', function () {
    // StoragePath requires Laravel Storage facade and configured disks
    // This test verifies the method signature works when storage is configured
    // Actual storage functionality is tested in feature tests
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturn(makeMockPrismResponse('Response'));

    // Use 'local' disk which is available in default Laravel config
    $this->request
        ->withImage('test.png', MediaSource::StoragePath, null, 'local')
        ->chat('What is this?');

    expect($capturedContext->prismMedia)->toHaveCount(1);
    expect($capturedContext->prismMedia[0])->toBeInstanceOf(Image::class);
})->skip('Requires configured storage disk - covered in feature tests');

// === withDocument ===

test('withDocument adds document attachment as Prism Document object', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturn(makeMockPrismResponse('Response'));

    $this->request
        ->withDocument('https://example.com/document.pdf')
        ->chat('Summarize this document');

    expect($capturedContext->prismMedia)->toHaveCount(1);
    expect($capturedContext->prismMedia[0])->toBeInstanceOf(Document::class);
});

test('withDocument creates Document with title', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturn(makeMockPrismResponse('Response'));

    $this->request
        ->withDocument('https://example.com/document.pdf', MediaSource::Url, 'application/pdf', 'Annual Report')
        ->chat('Summarize this');

    expect($capturedContext->prismMedia)->toHaveCount(1);
    expect($capturedContext->prismMedia[0])->toBeInstanceOf(Document::class);
});

// === withAudio ===

test('withAudio adds audio attachment as Prism Audio object', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturn(makeMockPrismResponse('Response'));

    $this->request
        ->withAudio('https://example.com/audio.mp3')
        ->chat('Transcribe this audio');

    expect($capturedContext->prismMedia)->toHaveCount(1);
    expect($capturedContext->prismMedia[0])->toBeInstanceOf(Audio::class);
});

// === withVideo ===

test('withVideo adds video attachment as Prism Video object', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturn(makeMockPrismResponse('Response'));

    $this->request
        ->withVideo('https://example.com/video.mp4')
        ->chat('What happens in this video?');

    expect($capturedContext->prismMedia)->toHaveCount(1);
    expect($capturedContext->prismMedia[0])->toBeInstanceOf(Video::class);
});

// === withMedia (Prism objects) ===

test('withMedia adds single Prism media object', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturn(makeMockPrismResponse('Response'));

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
        ->andReturn(makeMockPrismResponse('Response'));

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
        ->with($this->agent, 'Hello', Mockery::type(ExecutionContext::class))
        ->andReturn(makeMockPrismResponse('Hi there!'));

    $response = $this->request->chat('Hello');

    expect($response)->toBeInstanceOf(PrismResponse::class);
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
        ->andReturn(makeMockPrismResponse('Response'));

    $this->request->chat('Test input message');

    expect($capturedInput)->toBe('Test input message');
});

// === stream ===

test('stream resolves agent and returns generator', function () {
    $this->resolver->shouldReceive('resolve')
        ->once()
        ->with($this->agent)
        ->andReturn($this->agent);

    $this->executor->shouldReceive('stream')
        ->once()
        ->with($this->agent, 'Hello', Mockery::type(ExecutionContext::class))
        ->andReturn((function () {
            yield 'chunk1';
            yield 'chunk2';
        })());

    $result = $this->request->stream('Hello');

    expect($result)->toBeInstanceOf(Generator::class);

    $chunks = iterator_to_array($result);
    expect($chunks)->toBe(['chunk1', 'chunk2']);
});

// === Fluent chaining ===

test('all methods can be chained fluently', function () {
    $this->executor->shouldReceive('execute')
        ->once()
        ->andReturn(makeMockPrismResponse('Response'));

    $response = $this->request
        ->withMessages([['role' => 'user', 'content' => 'Previous']])
        ->withVariables(['name' => 'John'])
        ->withMetadata(['request_id' => '123'])
        ->withProvider('anthropic', 'claude-3-opus')
        ->withImage('https://example.com/image.png')
        ->usingTemperature(0.7)
        ->chat('Hello');

    expect($response)->toBeInstanceOf(PrismResponse::class);
});

test('chaining preserves all configuration', function () {
    $capturedContext = null;
    $this->executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($agent, $input, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        })
        ->andReturn(makeMockPrismResponse('Response'));

    $this->request
        ->withMessages([['role' => 'user', 'content' => 'Hi']])
        ->withVariables(['name' => 'John'])
        ->withMetadata(['id' => '123'])
        ->withProvider('anthropic')
        ->withModel('claude-3-opus')
        ->withImage('https://example.com/image.png')
        ->usingTemperature(0.7)
        ->chat('Hello');

    expect($capturedContext->messages)->toBe([['role' => 'user', 'content' => 'Hi']]);
    expect($capturedContext->variables)->toBe(['name' => 'John']);
    expect($capturedContext->metadata)->toBe(['id' => '123']);
    expect($capturedContext->providerOverride)->toBe('anthropic');
    expect($capturedContext->modelOverride)->toBe('claude-3-opus');
    expect($capturedContext->prismMedia)->toHaveCount(1);
    expect($capturedContext->prismMedia[0])->toBeInstanceOf(Image::class);
    expect($capturedContext->prismCalls)->toHaveCount(1);
});
