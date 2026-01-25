# Custom Providers

Atlas supports custom AI providers through Prism. Custom providers are registered at the Prism level and work transparently with Atlas.

::: tip Prism Reference
For detailed instructions on creating and registering custom providers, see the [Prism Custom Providers documentation](https://prismphp.com/advanced/custom-providers.html).
:::

## Using Custom Providers

Once a custom provider is registered with Prism, use it with Atlas like any built-in provider.

### With Agents

```php
// Define in agent class
public function provider(): string
{
    return 'my-custom-provider';
}

public function model(): string
{
    return 'custom-model-v1';
}
```

### Override at Runtime

```php
Atlas::agent('my-agent')
    ->withProvider('my-custom-provider', 'custom-model')
    ->chat('Hello');
```

### Direct Prism Usage

```php
// Text generation
Atlas::text()
    ->using('my-custom-provider', 'custom-model')
    ->withPrompt('Hello')
    ->asText();

// Images
Atlas::image()
    ->using('my-custom-provider', 'custom-model')
    ->withPrompt('A sunset')
    ->generate();

// Embeddings
Atlas::embeddings()
    ->using('my-custom-provider', 'custom-model')
    ->fromInput('Text to embed')
    ->asEmbeddings();
```

## Provider Options

Pass provider-specific options using `withProviderMeta()`:

```php
// Anthropic prompt caching
Atlas::agent('my-agent')
    ->withProviderMeta('anthropic', ['cacheType' => 'ephemeral'])
    ->chat('Hello');

// OpenAI options
Atlas::text()
    ->using('openai', 'gpt-4o')
    ->withProviderMeta('openai', [
        'presence_penalty' => 0.5,
        'frequency_penalty' => 0.3,
    ])
    ->withPrompt('Hello')
    ->asText();
```

## Provider Resolution Order

When determining which provider to use:

1. `withProvider()` override (highest priority)
2. Agent's `provider()` method

If neither is set, an exception is thrown.

```php
// Agent defines 'openai', but override to 'anthropic'
Atlas::agent('my-agent')
    ->withProvider('anthropic', 'claude-sonnet-4-20250514')
    ->chat('Hello');
```

## Next Steps

- [Chat](/capabilities/chat) — Chat configuration
- [Streaming](/capabilities/streaming) — Stream responses
- [Error Handling](/advanced/error-handling) — Handle provider errors
