# Custom Drivers

For providers that don't follow the OpenAI protocol, Atlas supports custom driver classes with full dependency injection. Create your own driver to talk to any API — you control the HTTP calls, payload format, response parsing, and streaming.

::: tip OpenAI Compatible?
If your provider exposes an OpenAI-compatible `/v1/chat/completions` endpoint, you don't need a custom driver. Use the [Custom Providers](/guides/custom-providers) guide instead.
:::

## How It Works

A custom driver extends `Driver` and implements handler interfaces for the modalities you need. Atlas provides clean contracts — your handler is a complete black box that controls everything about the API interaction.

## Creating a Custom Driver

### 1. Create the Driver

```php
namespace App\Atlas;

use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\Handlers\TextHandler;
use Atlasphp\Atlas\Providers\ProviderCapabilities;

class MyProviderDriver extends Driver
{
    public function name(): string
    {
        return 'my-provider';
    }

    public function capabilities(): ProviderCapabilities
    {
        return ProviderCapabilities::withOverrides(
            new ProviderCapabilities(text: true, stream: true),
            $this->config->capabilityOverrides,
        );
    }

    protected function textHandler(): TextHandler
    {
        return new MyTextHandler($this->config, $this->http);
    }
}
```

### 2. Create the Handler

Handlers implement handler interfaces — pure contracts that define input/output types. Atlas never dictates how your handler calls its API, parses responses, or handles streaming.

```php
namespace App\Atlas;

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Providers\Concerns\BuildsHeaders;
use Atlasphp\Atlas\Providers\Handlers\TextHandler;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;

class MyTextHandler implements TextHandler
{
    use BuildsHeaders;

    public function __construct(
        private readonly ProviderConfig $config,
        private readonly HttpClient $http,
    ) {}

    public function text(TextRequest $request): TextResponse
    {
        $response = $this->http->post(
            url: $this->config->baseUrl.'/generate',
            headers: $this->headers(),
            body: ['model' => $request->model, 'prompt' => $request->message],
            timeout: $this->config->timeout,
        );

        return new TextResponse(
            text: $response['output'] ?? '',
            finishReason: FinishReason::Stop,
            usage: new Usage(
                inputTokens: $response['usage']['input'] ?? 0,
                outputTokens: $response['usage']['output'] ?? 0,
            ),
        );
    }

    public function stream(TextRequest $request): StreamResponse
    {
        // See "Streaming" section below
        throw new \RuntimeException('Not implemented');
    }

    public function structured(TextRequest $request): StructuredResponse
    {
        throw new \RuntimeException('Not implemented');
    }
}
```

### 3. Register in Config

```php
// config/atlas.php → providers
'my-provider' => [
    'driver' => \App\Atlas\MyProviderDriver::class,
    'api_key' => env('MY_PROVIDER_API_KEY'),
    'base_url' => 'https://api.my-provider.com/v1',
    'capabilities' => ['text' => true, 'stream' => true],
],
```

The driver receives all dependencies via constructor injection: `ProviderConfig`, `HttpClient`, `MiddlewareStack`, and `AtlasCache`.

### 4. Use It

```php
$response = Atlas::text('my-provider', 'my-model')
    ->message('Hello from my custom provider')
    ->asText();
```

## Streaming

For streaming, your handler yields `StreamChunk` objects into a `StreamResponse`. Atlas handles SSE delivery, broadcasting, accumulation, and callbacks automatically — you just yield chunks.

```php
use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Providers\SseParser;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\Usage;

public function stream(TextRequest $request): StreamResponse
{
    $payload = $this->buildPayload($request);
    $payload['stream'] = true;

    $rawResponse = $this->http->stream(
        url: $this->config->baseUrl.'/generate',
        headers: $this->headers(),
        body: $payload,
        timeout: $this->config->timeout,
    );

    $generator = function () use ($rawResponse) {
        // You control: HTTP call, SSE parsing, chunk assembly — everything
        foreach (SseParser::parse($rawResponse) as $event) {
            $delta = $event['data']['delta'] ?? '';

            if ($delta !== '') {
                yield new StreamChunk(
                    type: ChunkType::Text,
                    text: $delta,
                );
            }
        }

        // Replace with actual accumulated token counts from the stream
        yield new StreamChunk(
            type: ChunkType::Done,
            usage: new Usage($inputTokens, $outputTokens),
            finishReason: FinishReason::Stop,
        );
    };

    return new StreamResponse($generator());
}
```

## Handler Interfaces

Each modality has a handler interface. Implement only the ones your provider supports.

| Interface | Methods | Input → Output |
|-----------|---------|---------------|
| `TextHandler` | `text()`, `stream()`, `structured()` | `TextRequest` → `TextResponse` / `StreamResponse` / `StructuredResponse` |
| `AudioHandler` | `audio()`, `audioToText()` | `AudioRequest` → `AudioResponse` / `TextResponse` |
| `ImageHandler` | `image()`, `imageToText()` | `ImageRequest` → `ImageResponse` / `TextResponse` |
| `VideoHandler` | `video()`, `videoToText()` | `VideoRequest` → `VideoResponse` / `TextResponse` |
| `EmbedHandler` | `embed()` | `EmbedRequest` → `EmbeddingsResponse` |
| `ModerateHandler` | `moderate()` | `ModerateRequest` → `ModerationResponse` |
| `RerankHandler` | `rerank()` | `RerankRequest` → `RerankResponse` |
| `ProviderHandler` | `models()`, `voices()`, `validate()` | → `ModelList` / `VoiceList` / `bool` |

## Handler Overrides

Use `withHandler()` to add or replace a handler on any existing driver without subclassing. This is useful when you want to extend a built-in driver with a modality it doesn't natively support.

::: warning Handler Keys Map to Interfaces, Not Methods
Override keys correspond to handler interfaces. The `'text'` key controls `text()`, `stream()`, and `structured()` — all three are methods on `TextHandler`. Similarly, `'audio'` controls both `audio()` and `audioToText()`. You cannot override `stream` or `structured` independently from `text`.

| Key | Handler Interface | Methods Covered |
|-----|-------------------|----------------|
| `'text'` | `TextHandler` | `text()`, `stream()`, `structured()` |
| `'image'` | `ImageHandler` | `image()`, `imageToText()` |
| `'audio'` | `AudioHandler` | `audio()`, `audioToText()` |
| `'video'` | `VideoHandler` | `video()`, `videoToText()` |
| `'embed'` | `EmbedHandler` | `embed()` |
| `'moderate'` | `ModerateHandler` | `moderate()` |
| `'rerank'` | `RerankHandler` | `rerank()` |
| `'provider'` | `ProviderHandler` | `models()`, `voices()`, `validate()` |
:::

### Adding a Handler via Factory Registration

```php
// In a service provider
use Atlasphp\Atlas\Facades\Atlas;

Atlas::providers()->register('my-tts', function ($app, $config) {
    $providerConfig = \Atlasphp\Atlas\Providers\ProviderConfig::fromArray($config);

    return (new \Atlasphp\Atlas\Providers\ChatCompletions\ChatCompletionsDriver(
        config: $providerConfig,
        http: $app->make(\Atlasphp\Atlas\Providers\HttpClient::class),
        middlewareStack: $app->make(\Atlasphp\Atlas\Middleware\MiddlewareStack::class),
    ))->withHandler('audio', new MyTtsHandler($providerConfig));
});
```

### Replacing a Handler on a Resolved Driver

```php
$driver = Atlas::providers()->resolve('openai');

$customDriver = $driver->withHandler('text', new MyCustomTextHandler());
```

`withHandler()` returns a new driver instance — the original is unchanged. Multiple calls stack independently:

```php
$driver = $baseDriver
    ->withHandler('text', new MyTextHandler())
    ->withHandler('audio', new MyAudioHandler());
```

## Available Utilities

Your handlers can optionally reuse Atlas utilities — none are required:

- `HttpClient` — shared HTTP transport with event dispatching
- `BuildsHeaders` trait — Bearer auth + Content-Type headers
- `SseParser` — generic SSE stream parser
- `ProviderConfig` — access to `apiKey`, `baseUrl`, `timeout`, `extra` bag
- `ResolvesMediaUri` / `ResolvesAudioFile` traits — media format conversion

## Capability Overrides

Custom drivers should use `ProviderCapabilities::withOverrides()` in their `capabilities()` method to respect config-level overrides:

```php
public function capabilities(): ProviderCapabilities
{
    return ProviderCapabilities::withOverrides(
        new ProviderCapabilities(text: true, stream: true),
        $this->config->capabilityOverrides,
    );
}
```

Consumers override capabilities in config:

```php
'my-provider' => [
    'driver' => \App\Atlas\MyProviderDriver::class,
    'api_key' => env('MY_PROVIDER_API_KEY'),
    'base_url' => 'https://api.my-provider.com/v1',
    'capabilities' => ['text' => true, 'stream' => true, 'audio' => true],
],
```
