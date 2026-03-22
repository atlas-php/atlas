# Models & Provider Info

Query providers for their available models, voices, and capabilities.

## List Models

```php
use Atlasphp\Atlas\Facades\Atlas;

$models = Atlas::provider('openai')->models();

foreach ($models as $model) {
    echo $model->id . "\n";
}
```

## List Voices

```php
$voices = Atlas::provider('openai')->voices();

foreach ($voices as $voice) {
    echo "{$voice->id}: {$voice->name}\n";
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

Model and voice listings are cached by default:

```env
ATLAS_CACHE_MODELS_TTL=86400   # 24 hours
ATLAS_CACHE_VOICES_TTL=3600    # 1 hour
```

Set TTL to 0 to disable caching.

## Supported Providers

All configured providers support `models()` and `validate()`. Voice listing is available on providers that support audio (OpenAI, ElevenLabs).

## API Reference

| Method | Returns | Description |
|--------|---------|-------------|
| `models()` | `ModelList` | List available models |
| `voices()` | `VoiceList` | List available voices |
| `validate()` | `bool` | Check provider connectivity |
| `capabilities()` | `ProviderCapabilities` | Check supported features |
| `name()` | `string` | Provider identifier |
