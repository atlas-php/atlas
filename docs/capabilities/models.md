# Models

List available models from AI provider APIs with automatic caching.

::: tip First of Its Kind
Neither Prism nor any other PHP AI SDK provides a way to list available models from providers. Atlas is the first to ship this capability.
:::

## Supported Providers

<div class="full-width-table">

| Provider | Supported | Format |
|----------|-----------|--------|
| OpenAI | Yes | OpenAI-compatible |
| Anthropic | Yes | Anthropic |
| Gemini | Yes | Gemini |
| Ollama | Yes | OpenAI-compatible with native fallback |
| DeepSeek | Yes | OpenAI-compatible |
| Mistral | Yes | OpenAI-compatible |
| Groq | Yes | OpenAI-compatible |
| XAI | Yes | OpenAI-compatible |
| OpenRouter | Yes | OpenAI-compatible |
| ElevenLabs | Yes | ElevenLabs |
| Perplexity | No | No models endpoint |
| VoyageAI | No | No models endpoint |
| Z | No | No models endpoint |

</div>

## Basic Usage

```php
use Atlasphp\Atlas\Atlas;

// List models from a specific provider
$models = Atlas::models('openai')->all();

// Returns a simple sorted list of model identifiers
foreach ($models as $model) {
    echo $model; // e.g., "gpt-4o"
}
```

## Using Prism Provider Enum

```php
use Prism\Prism\Enums\Provider;

$models = Atlas::models(Provider::Anthropic)->all();
```

## Check Provider Support

```php
if (Atlas::models('elevenlabs')->has()) {
    // Won't reach here — ElevenLabs has no models endpoint
}
```

## Caching

Model lists are cached by default since they change infrequently. Configure in `config/atlas.php`:

```php
'models' => [
    'cache' => [
        'enabled' => true,    // Enable/disable caching
        'store' => null,      // null = default cache store
        'ttl' => 3600,        // Cache lifetime in seconds
    ],
],
```

### Force Refresh

```php
// Bypass cache and fetch fresh from the API
$models = Atlas::models('openai')->refresh();
```

### Clear Cache

```php
// Clear cached models for a specific provider
Atlas::models('openai')->clear();
```

## Return Format

All methods return `list<string>` — a sorted list of model identifier strings (e.g., `['gpt-3.5-turbo', 'gpt-4o']`). These are the identifiers you pass to `->using()` when making API calls.

## OpenAI-Compatible Providers

Most providers (Groq, DeepSeek, Mistral, XAI, OpenRouter, Perplexity, VoyageAI) use the OpenAI-compatible `/v1/models` endpoint format. If you're using a custom OpenAI-compatible provider, it will work automatically as long as it follows the same response format.

## API Reference

```php
// All methods require a provider
Atlas::models(Provider|string $provider)

// Get models (cached or fresh)
->all(): ?array

// Check if provider supports model listing
->has(): bool

// Force refresh from API
->refresh(): ?array

// Clear cached models
->clear(): void
```

## Pipeline Hooks

Model listing does not currently participate in the pipeline system. This may be added in a future version if observability hooks around model fetching prove useful.

## Next Steps

- [Chat](/capabilities/chat) — Use listed models for chat requests
- [Embeddings](/capabilities/embeddings) — Use listed models for embeddings
- [Pipelines](/core-concepts/pipelines) — Add observability hooks
