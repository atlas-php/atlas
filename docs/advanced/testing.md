# Testing

Atlas provides a first-class testing system built around `Atlas::fake()`. Fake the entire provider layer, queue up custom responses, and assert against recorded requests — no HTTP calls, no API keys.

## Quick Start

```php
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Testing\TextResponseFake;

public function test_support_agent_responds(): void
{
    $fake = Atlas::fake([
        TextResponseFake::make()->withText('How can I help?'),
    ]);

    $response = Atlas::text('openai', 'gpt-4o')
        ->message('Hello')
        ->asText();

    $this->assertEquals('How can I help?', $response->text);

    $fake->assertSent();
    $fake->assertSentCount(1);
    $fake->assertMessageContains('Hello');
}
```

`Atlas::fake()` replaces all provider drivers with fakes, records every request, and returns an `AtlasFake` instance you use for assertions.

## Custom Responses

### Providing Responses

Pass response fakes to `Atlas::fake()` as an array. They are consumed in sequence — each call gets the next response. When the sequence is exhausted, the last response repeats.

```php
$fake = Atlas::fake([
    TextResponseFake::make()->withText('First'),
    TextResponseFake::make()->withText('Second'),
]);

Atlas::text('openai', 'gpt-4o')->message('A')->asText(); // "First"
Atlas::text('openai', 'gpt-4o')->message('B')->asText(); // "Second"
Atlas::text('openai', 'gpt-4o')->message('C')->asText(); // "Second" (repeats)
```

### Default Responses

Call `Atlas::fake()` with no arguments to use sensible defaults for every modality:

```php
$fake = Atlas::fake();

// Text calls return empty text with Usage(10, 20) and FinishReason::Stop
// Image calls return 'https://fake.atlas/image.png'
// Audio calls return base64-encoded 'fake-audio' in mp3 format
// Embeddings return [[0.1, 0.2, 0.3]]
// And so on — every modality has a zero-config default
```

### Response Fake Builders

Each modality has a fluent builder. Call `make()` to start, chain `with*()` methods to customize, and either pass the builder directly to `Atlas::fake()` or call `toResponse()` when you need the response object.

#### TextResponseFake

```php
use Atlasphp\Atlas\Testing\TextResponseFake;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Responses\Usage;

TextResponseFake::make()
    ->withText('Hello world')
    ->withUsage(new Usage(15, 30))
    ->withFinishReason(FinishReason::Stop)
    ->withToolCalls([$toolCall])
    ->withReasoning('Let me think...')
    ->withMeta(['id' => 'chatcmpl-123'])
    ->withProviderToolCalls([['type' => 'web_search_call', 'id' => 'ws_1', 'status' => 'completed']])
    ->withAnnotations([['type' => 'url_citation', 'url' => 'https://example.com', 'title' => 'Example']]);
```

Default: `text=''`, `usage=Usage(10, 20)`, `finishReason=Stop`

#### StreamResponseFake

```php
use Atlasphp\Atlas\Testing\StreamResponseFake;

StreamResponseFake::make()
    ->withText('Streamed content here')
    ->withChunkSize(10)
    ->withUsage(new Usage(15, 30))
    ->withFinishReason(FinishReason::Stop);
```

Splits text into chunks of `chunkSize` characters, then emits a final `Done` chunk with usage and finish reason. Default: `text=''`, `chunkSize=5`

#### StructuredResponseFake

```php
use Atlasphp\Atlas\Testing\StructuredResponseFake;

StructuredResponseFake::make()
    ->withStructured(['sentiment' => 'positive', 'score' => 0.95])
    ->withUsage(new Usage(10, 20))
    ->withFinishReason(FinishReason::Stop);
```

#### ImageResponseFake

```php
use Atlasphp\Atlas\Testing\ImageResponseFake;

ImageResponseFake::make()
    ->withUrl('https://example.com/generated.png')
    ->withRevisedPrompt('A scenic mountain landscape at sunset')
    ->withMeta(['model' => 'dall-e-3']);
```

Default: `url='https://fake.atlas/image.png'`

#### AudioResponseFake

```php
use Atlasphp\Atlas\Testing\AudioResponseFake;

AudioResponseFake::make()
    ->withData(base64_encode('custom-audio-data'))
    ->withFormat('wav')
    ->withMeta(['duration' => 3.5]);
```

Default: `data=base64('fake-audio')`, `format='mp3'`

#### VideoResponseFake

```php
use Atlasphp\Atlas\Testing\VideoResponseFake;

VideoResponseFake::make()
    ->withUrl('https://example.com/video.mp4')
    ->withDuration(30)
    ->withMeta(['resolution' => '1080p']);
```

Default: `url='https://fake.atlas/video.mp4'`

#### EmbeddingsResponseFake

```php
use Atlasphp\Atlas\Testing\EmbeddingsResponseFake;

EmbeddingsResponseFake::make()
    ->withEmbeddings([
        [0.1, 0.2, 0.3, 0.4],
        [0.5, 0.6, 0.7, 0.8],
    ])
    ->withUsage(new Usage(10, 0));
```

Default: `embeddings=[[0.1, 0.2, 0.3]]`, `usage=Usage(5, 0)`

#### ModerationResponseFake

```php
use Atlasphp\Atlas\Testing\ModerationResponseFake;

ModerationResponseFake::make()
    ->withFlagged(true)
    ->withCategories(['hate' => true, 'violence' => false])
    ->withMeta(['model' => 'text-moderation-latest']);
```

Default: `flagged=false`, `categories=[]`

#### RerankResponseFake

```php
use Atlasphp\Atlas\Testing\RerankResponseFake;
use Atlasphp\Atlas\Responses\RerankResult;

// Using defaults (3 results with scores 0.95, 0.80, 0.60)
RerankResponseFake::make();

// Custom count with auto-generated scores
RerankResponseFake::withCount(5);

// Custom count with specific scores
RerankResponseFake::withCount(3, [0.99, 0.75, 0.50]);

// Full control
RerankResponseFake::make()
    ->withResults([
        new RerankResult(0, 0.99, 'Most relevant document'),
        new RerankResult(2, 0.85, 'Second most relevant'),
    ])
    ->withMeta(['model' => 'rerank-v3']);
```

Default: 3 results with scores 0.95, 0.80, 0.60

## Assertions

All assertion methods are on the `AtlasFake` instance returned by `Atlas::fake()`.

```php
$fake = Atlas::fake();

// ... make calls ...

// Was anything sent?
$fake->assertSent();
$fake->assertNothingSent();
$fake->assertSentCount(3);

// Match requests with a callback
$fake->assertSentWith(fn (RecordedRequest $r) => $r->method === 'text');

// Assert provider and model
$fake->assertSentTo('openai', 'gpt-4o');

// Assert a specific method was called
$fake->assertMethodCalled('text');
$fake->assertMethodCalled('image');
$fake->assertMethodCalled('embed');

// Assert instructions content
$fake->assertInstructionsContain('You are a helpful assistant');

// Assert message content
$fake->assertMessageContains('hello');
```

## Testing Agents

### Agent Configuration

Test agent properties directly — no faking needed:

```php
use App\Agents\CustomerSupportAgent;

public function test_agent_configuration(): void
{
    $agent = new CustomerSupportAgent();

    $this->assertEquals('customer-support', $agent->key());
    $this->assertEquals('openai', $agent->provider());
    $this->assertEquals('gpt-4o', $agent->model());
    $this->assertStringContainsString('customer support', $agent->systemPrompt());
}

public function test_agent_has_required_tools(): void
{
    $agent = new CustomerSupportAgent();

    $this->assertContains(LookupOrderTool::class, $agent->tools());
    $this->assertContains(SearchProductsTool::class, $agent->tools());
}
```

### Agent Execution

Fake Atlas, run the agent through the normal flow, and assert:

```php
public function test_agent_responds_to_user(): void
{
    $fake = Atlas::fake([
        TextResponseFake::make()->withText('I can help with your order.'),
    ]);

    $response = Atlas::agent('support')
        ->for($this->user)
        ->message('Where is my order?')
        ->asText();

    $this->assertEquals('I can help with your order.', $response->text);
    $fake->assertSent();
    $fake->assertMethodCalled('text');
}
```

## Testing Tools

Test tool `handle()` methods directly as unit tests. Tools are plain classes — no provider interaction needed.

```php
use App\Tools\LookupOrderTool;

public function test_returns_order_when_found(): void
{
    $order = Order::factory()->create([
        'id' => 'ORD-123',
        'status' => 'shipped',
    ]);

    $tool = new LookupOrderTool();
    $result = $tool->handle(['order_id' => 'ORD-123']);

    $this->assertTrue($result->succeeded());
    $this->assertStringContainsString('shipped', $result->toText());
}

public function test_returns_error_when_not_found(): void
{
    $tool = new LookupOrderTool();
    $result = $tool->handle(['order_id' => 'INVALID']);

    $this->assertTrue($result->failed());
    $this->assertStringContainsString('not found', $result->toText());
}
```

## Testing Structured Output

```php
use Atlasphp\Atlas\Testing\StructuredResponseFake;

public function test_extracts_sentiment(): void
{
    $fake = Atlas::fake([
        StructuredResponseFake::make()->withStructured([
            'sentiment' => 'positive',
            'confidence' => 0.95,
        ]),
    ]);

    $response = Atlas::structured('openai', 'gpt-4o')
        ->schema(
            Schema::object('analysis')
                ->string('sentiment', 'The detected sentiment')
                ->number('confidence', 'Confidence score')
        )
        ->message('This product is amazing!')
        ->asStructured();

    $this->assertEquals('positive', $response->structured['sentiment']);
    $fake->assertMethodCalled('structured');
}
```

## Testing Streaming

```php
use Atlasphp\Atlas\Testing\StreamResponseFake;

public function test_streaming_response(): void
{
    $fake = Atlas::fake([
        StreamResponseFake::make()
            ->withText('Hello, this is a streamed response!')
            ->withChunkSize(10),
    ]);

    $response = Atlas::text('openai', 'gpt-4o')
        ->message('Hello')
        ->asStream();

    $chunks = [];
    foreach ($response as $chunk) {
        if ($chunk->text !== null) {
            $chunks[] = $chunk->text;
        }
    }

    $this->assertNotEmpty($chunks);
    $fake->assertMethodCalled('stream');
}
```

A `TextResponseFake` passed to a stream call is automatically converted to a `StreamResponseFake`, so you can use either.

## Testing All Modalities

### Image Generation

```php
use Atlasphp\Atlas\Testing\ImageResponseFake;

public function test_image_generation(): void
{
    $fake = Atlas::fake([
        ImageResponseFake::make()
            ->withUrl('https://example.com/cat.png')
            ->withRevisedPrompt('A fluffy orange cat'),
    ]);

    $response = Atlas::image('openai', 'dall-e-3')
        ->prompt('Draw a cat')
        ->asImage();

    $this->assertEquals('https://example.com/cat.png', $response->url);
    $fake->assertMethodCalled('image');
}
```

### Audio Generation

```php
use Atlasphp\Atlas\Testing\AudioResponseFake;

public function test_audio_generation(): void
{
    $fake = Atlas::fake([
        AudioResponseFake::make()
            ->withFormat('mp3')
            ->withData(base64_encode('test-audio')),
    ]);

    $response = Atlas::audio('openai', 'tts-1')
        ->input('Hello world')
        ->asAudio();

    $this->assertEquals('mp3', $response->format);
    $fake->assertMethodCalled('audio');
}
```

### Video Generation

```php
use Atlasphp\Atlas\Testing\VideoResponseFake;

public function test_video_generation(): void
{
    $fake = Atlas::fake([
        VideoResponseFake::make()
            ->withUrl('https://example.com/video.mp4')
            ->withDuration(15),
    ]);

    $response = Atlas::video('openai', 'sora')
        ->prompt('A sunset over the ocean')
        ->asVideo();

    $this->assertEquals('https://example.com/video.mp4', $response->url);
    $fake->assertMethodCalled('video');
}
```

### Embeddings

```php
use Atlasphp\Atlas\Testing\EmbeddingsResponseFake;

public function test_embeddings(): void
{
    $fake = Atlas::fake([
        EmbeddingsResponseFake::make()->withEmbeddings([
            [0.1, 0.2, 0.3],
            [0.4, 0.5, 0.6],
        ]),
    ]);

    $response = Atlas::embed('openai', 'text-embedding-3-small')
        ->input(['Hello', 'World'])
        ->asEmbeddings();

    $this->assertCount(2, $response->embeddings);
    $fake->assertMethodCalled('embed');
}
```

### Moderation

```php
use Atlasphp\Atlas\Testing\ModerationResponseFake;

public function test_moderation(): void
{
    $fake = Atlas::fake([
        ModerationResponseFake::make()
            ->withFlagged(true)
            ->withCategories(['hate' => true]),
    ]);

    $response = Atlas::moderate('openai', 'text-moderation-latest')
        ->input('Some content')
        ->asModeration();

    $this->assertTrue($response->flagged);
    $fake->assertMethodCalled('moderate');
}
```

### Reranking

```php
use Atlasphp\Atlas\Testing\RerankResponseFake;

public function test_reranking(): void
{
    $fake = Atlas::fake([
        RerankResponseFake::withCount(3, [0.95, 0.80, 0.60]),
    ]);

    $response = Atlas::rerank('cohere', 'rerank-v3')
        ->query('What is Laravel?')
        ->documents(['Laravel is a framework', 'PHP is a language', 'Dogs are pets'])
        ->asRerank();

    $this->assertCount(3, $response->results);
    $this->assertEquals(0.95, $response->results[0]->score);
    $fake->assertMethodCalled('rerank');
}
```

## Recorded Requests

Every call through `Atlas::fake()` is recorded as a `RecordedRequest`. Use this for fine-grained inspection.

```php
$fake = Atlas::fake();

Atlas::text('openai', 'gpt-4o')->message('Hello')->asText();
Atlas::text('anthropic', 'claude-4-sonnet')->message('Hi')->asText();

$recorded = $fake->recorded(); // Array of RecordedRequest

$recorded[0]->method;    // 'text'
$recorded[0]->provider;  // 'openai'
$recorded[0]->model;     // 'gpt-4o'
$recorded[0]->request;   // The TextRequest object
```

### Per-Driver Access

Access the fake driver for a specific provider to inspect only that provider's requests:

```php
$fake = Atlas::fake();

Atlas::text('openai', 'gpt-4o')->message('Hello')->asText();
Atlas::text('anthropic', 'claude-4-sonnet')->message('Hi')->asText();

$openaiDriver = $fake->driver('openai');
$openaiRecorded = $openaiDriver->recorded();

$this->assertCount(1, $openaiRecorded);
$this->assertEquals('gpt-4o', $openaiRecorded[0]->model);
```

## API Reference

### Atlas::fake()

| Signature | Returns | Description |
|-----------|---------|-------------|
| `Atlas::fake()` | `AtlasFake` | Fake with default responses for all modalities |
| `Atlas::fake(array $responses)` | `AtlasFake` | Fake with custom response sequence |

### AtlasFake Assertions

| Method | Description |
|--------|-------------|
| `assertSent()` | At least one request was sent |
| `assertNothingSent()` | No requests were sent |
| `assertSentCount(int $count)` | Exact number of requests sent |
| `assertSentWith(Closure $callback)` | A request matching the callback was sent |
| `assertSentTo(string $provider, string $model)` | Request sent to specific provider and model |
| `assertMethodCalled(string $method)` | A specific method was called (text, stream, image, etc.) |
| `assertInstructionsContain(string $text)` | A request's instructions contain the given text |
| `assertMessageContains(string $text)` | A request's message contains the given text |

### AtlasFake Access

| Method | Returns | Description |
|--------|---------|-------------|
| `recorded()` | `array<RecordedRequest>` | All recorded requests across all drivers |
| `driver(string $provider)` | `FakeDriver` | Access a specific provider's fake driver |

### RecordedRequest Properties

| Property | Type | Description |
|----------|------|-------------|
| `method` | `string` | The method called (text, stream, structured, image, audio, video, embed, moderate, rerank) |
| `provider` | `string` | Provider name (openai, anthropic, etc.) |
| `model` | `string` | Model identifier |
| `request` | `mixed` | The original request object (TextRequest, ImageRequest, etc.) |

### Response Fake Builders

| Builder | Primary Methods | Default |
|---------|----------------|---------|
| `TextResponseFake` | `withText`, `withUsage`, `withFinishReason`, `withToolCalls`, `withReasoning`, `withMeta`, `withProviderToolCalls`, `withAnnotations` | text='', usage=Usage(10,20) |
| `StreamResponseFake` | `withText`, `withChunkSize`, `withUsage`, `withFinishReason` | text='', chunkSize=5 |
| `StructuredResponseFake` | `withStructured`, `withUsage`, `withFinishReason` | structured=[], usage=Usage(10,20) |
| `ImageResponseFake` | `withUrl`, `withRevisedPrompt`, `withMeta` | url='https://fake.atlas/image.png' |
| `AudioResponseFake` | `withData`, `withFormat`, `withMeta` | data=base64('fake-audio'), format='mp3' |
| `VideoResponseFake` | `withUrl`, `withDuration`, `withMeta` | url='https://fake.atlas/video.mp4' |
| `EmbeddingsResponseFake` | `withEmbeddings`, `withUsage` | embeddings=[[0.1,0.2,0.3]], usage=Usage(5,0) |
| `ModerationResponseFake` | `withFlagged`, `withCategories`, `withMeta` | flagged=false |
| `RerankResponseFake` | `withCount`, `withResults`, `withMeta` | 3 results (0.95, 0.80, 0.60) |

All builders expose `make()` (static constructor) and `toResponse()` (build the response object).

## Next Steps

- [Agents](/features/agents) — Build testable agents
- [Tools](/features/tools) — Build testable tools
- [Error Handling](/advanced/error-handling) — Test error scenarios
