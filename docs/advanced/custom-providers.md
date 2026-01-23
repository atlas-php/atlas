# Custom Providers

Atlas supports custom AI providers through its underlying Prism integration. This guide explains how to use custom providers and configure provider-specific options.

## Custom Provider Support

Atlas uses [Prism](https://github.com/prism-php/prism) as its AI provider abstraction layer. Custom providers are registered at the Prism level and work transparently with Atlas.

To register a custom provider, follow the [Prism documentation](https://prismphp.org/custom-providers) for creating and registering providers. Once registered with Prism, you can use your custom provider with Atlas like any built-in provider:

```php
// Use custom provider by name
Atlas::agent('my-agent')
    ->withProvider('my-custom-provider', 'custom-model')
    ->chat('Hello');

// Use for images
Atlas::image()
    ->withProvider('my-custom-provider')
    ->generate('A sunset');
```

## Conditional Provider Configuration

Atlas provides the `whenProvider()` method for applying configuration only when a specific provider is active. This is useful for provider-specific options that don't apply universally.

### Basic Usage

```php
use Atlasphp\Atlas\Facades\Atlas;

Atlas::agent('my-agent')
    ->whenProvider('anthropic', fn($r) => $r
        ->withProviderOptions(['cacheType' => 'ephemeral']))
    ->whenProvider('openai', fn($r) => $r
        ->withProviderOptions(['presence_penalty' => 0.5]))
    ->chat('Hello');
```

The callback is only executed when the resolved provider matches.

### With Provider Enum

You can use Prism's `Provider` enum instead of strings:

```php
use Prism\Prism\Enums\Provider;

Atlas::agent('my-agent')
    ->whenProvider(Provider::Anthropic, fn($r) => $r
        ->withProviderOptions(['cacheType' => 'ephemeral']))
    ->chat('Hello');
```

### Multiple Provider Configurations

Chain multiple `whenProvider()` calls to configure different providers:

```php
Atlas::agent('my-agent')
    ->whenProvider('anthropic', fn($r) => $r
        ->withProviderOptions([
            'cacheType' => 'ephemeral',
        ]))
    ->whenProvider('openai', fn($r) => $r
        ->withProviderOptions([
            'presence_penalty' => 0.5,
            'frequency_penalty' => 0.3,
        ]))
    ->whenProvider('gemini', fn($r) => $r
        ->withProviderOptions([
            'safety_settings' => ['harm_block_threshold' => 'high'],
        ]))
    ->chat('Hello');
```

Only the matching provider's callback will execute.

### With Provider Override

Provider resolution follows this order:
1. `withProvider()` override
2. Agent's configured provider
3. Config default (`atlas.chat.provider`)

```php
// Config says 'openai', but withProvider overrides to 'anthropic'
// So the anthropic callback will execute
Atlas::agent('my-agent')
    ->withProvider('anthropic')
    ->whenProvider('anthropic', fn($r) => $r
        ->withProviderOptions(['cacheType' => 'ephemeral']))
    ->whenProvider('openai', fn($r) => $r
        ->withProviderOptions(['presence_penalty' => 0.5]))
    ->chat('Hello');
```

## Works With All Capabilities

### Chat/Agents

```php
Atlas::agent('my-agent')
    ->whenProvider('anthropic', fn($r) => $r
        ->withProviderOptions(['cacheType' => 'ephemeral']))
    ->chat('Hello');
```

### Image Generation

```php
Atlas::image()
    ->whenProvider('openai', fn($r) => $r
        ->withProviderOptions(['style' => 'vivid']))
    ->generate('A beautiful sunset');
```

### Embeddings

```php
Atlas::embedding()
    ->whenProvider('openai', fn($r) => $r
        ->withProviderOptions(['dimensions' => 256]))
    ->generate('Text to embed');
```

### Speech

```php
// Text-to-speech
Atlas::speech()
    ->whenProvider('openai', fn($r) => $r
        ->withProviderOptions(['speed' => 1.2]))
    ->generate('Hello world');

// Speech-to-text
Atlas::speech()
    ->whenProvider('openai', fn($r) => $r
        ->withProviderOptions(['language' => 'en']))
    ->transcribe('/path/to/audio.mp3');
```

### Moderation

```php
Atlas::moderation()
    ->whenProvider('openai', fn($r) => $r
        ->withProviderOptions(['include_raw' => true]))
    ->moderate('Text to moderate');
```

## Callback Chaining

Multiple callbacks for the same provider execute in order:

```php
Atlas::agent('my-agent')
    ->whenProvider('anthropic', fn($r) => $r
        ->withProviderOptions(['cacheType' => 'ephemeral']))
    ->whenProvider('anthropic', fn($r) => $r
        ->withMetadata(['tracking' => true]))
    ->chat('Hello');
```

Both callbacks apply when the provider is 'anthropic'.

## Common Provider Options

### Anthropic

```php
->withProviderOptions([
    'cacheType' => 'ephemeral',  // Enable prompt caching
])
```

### OpenAI

```php
->withProviderOptions([
    'presence_penalty' => 0.5,   // Reduce repetition
    'frequency_penalty' => 0.3,  // Reduce frequency of tokens
    'response_format' => ['type' => 'json_object'],  // Force JSON output
])
```

### Custom Providers

Pass any options your custom provider accepts:

```php
->withProviderOptions([
    'custom_option' => 'value',
    'another_option' => 123,
])
```

## Next Steps

- [Chat](/capabilities/chat) - Learn about chat configuration
- [Streaming](/capabilities/streaming) - Stream responses
- [Error Handling](/advanced/error-handling) - Handle provider errors
