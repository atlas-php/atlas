# Models

List available models from any configured provider. Useful for building model selectors, validating configurations, and checking provider capabilities.

## List Models

```php
use Atlasphp\Atlas\Facades\Atlas;

$models = Atlas::provider('openai')->models();

foreach ($models as $model) {
    echo $model->id . "\n";
}
```

## Validate Provider

Check if a provider is correctly configured and reachable:

```php
$valid = Atlas::provider('openai')->validate();

if ($valid) {
    echo 'Provider is configured and reachable.';
}
```

## Check Capabilities

Query what a provider supports before making calls:

```php
$capabilities = Atlas::provider('openai')->capabilities();

$capabilities->supports('text');       // true
$capabilities->supports('image');      // true
$capabilities->supports('audio');      // true
$capabilities->supports('embed');      // true
$capabilities->supports('rerank');     // false
```

## Provider Name

```php
$name = Atlas::provider('openai')->name();  // 'openai'
```

## Caching

Model listings are cached by default to minimize API calls:

```env
ATLAS_CACHE_MODELS_TTL=86400   # 24 hours (default)
```

Set TTL to `0` to disable caching.

## Supported Providers

| Provider | Models | Validate |
|----------|--------|----------|
| OpenAI | Yes | Yes |
| Anthropic | Yes | Yes |
| Google | Yes | Yes |
| xAI | Yes | Yes |
| ElevenLabs | Yes | Yes |

## API Reference

| Method | Returns | Description |
|--------|---------|-------------|
| `models()` | `ModelList` | List available models |
| `validate()` | `bool` | Check provider connectivity |
| `capabilities()` | `ProviderCapabilities` | Check supported features |
| `name()` | `string` | Provider identifier |
