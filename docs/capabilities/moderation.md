# Moderation

Analyze text content for policy violations and harmful content.

## Quick Example

```php
use Atlasphp\Atlas\Facades\Atlas;

$response = Atlas::moderate('openai', 'omni-moderation-latest')
    ->fromInput('This is a normal message.')
    ->asModeration();

echo $response->flagged ? 'Flagged' : 'Safe';  // "Safe"
```

## Checking Content

```php
$response = Atlas::moderate('openai', 'omni-moderation-latest')
    ->fromInput('Some user-generated content to check...')
    ->asModeration();

$response->flagged;      // bool — whether content was flagged
$response->categories;   // array — category scores and flags
```

## Category Details

The categories array contains per-category flagging:

```php
$response = Atlas::moderate('openai', 'omni-moderation-latest')
    ->fromInput($userInput)
    ->asModeration();

foreach ($response->categories as $category => $data) {
    if ($data['flagged']) {
        logger()->warning("Content flagged for: {$category}", [
            'score' => $data['score'],
        ]);
    }
}
```

## Practical Usage

### Middleware

```php
// In a controller or middleware
$moderation = Atlas::moderate('openai', 'omni-moderation-latest')
    ->fromInput($request->input('message'))
    ->asModeration();

if ($moderation->flagged) {
    return response()->json(['error' => 'Content violates policy'], 422);
}
```

## Supported Providers

| Provider | Models |
|----------|--------|
| OpenAI | omni-moderation-latest, text-moderation-latest, text-moderation-stable |

## ModerationResponse

| Property | Type | Description |
|----------|------|-------------|
| `flagged` | `bool` | Whether the content was flagged |
| `categories` | `array` | Per-category results with scores and flags |
| `meta` | `array` | Additional metadata |

## Builder Reference

| Method | Description |
|--------|-------------|
| `fromInput(string\|array)` | Content to moderate |
| `withProviderOptions(array)` | Provider-specific options |
| `withMeta(array)` | Metadata for middleware/events |
| `withMiddleware(array)` | Per-request provider middleware |
| `queue()` | Dispatch to queue |
