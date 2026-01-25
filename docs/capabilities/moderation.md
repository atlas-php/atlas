# Moderation

Content moderation for detecting potentially harmful or inappropriate content.

::: tip Prism Reference
Atlas moderation wraps Prism's moderation API. For detailed documentation including categories and response objects, see [Prism Moderation](https://prismphp.com/core-concepts/moderation.html).
:::

## Basic Usage

```php
use Atlasphp\Atlas\Atlas;

$response = Atlas::moderation()
    ->using('openai', 'omni-moderation-latest')
    ->fromInput('Text to check for violations')
    ->asModeration();

if ($response->isFlagged()) {
    // Content was flagged
}
```

::: warning Provider Support
Currently only OpenAI supports content moderation. Other providers will throw an unsupported exception.
:::

## Batch Moderation

Moderate multiple inputs in a single request:

```php
$response = Atlas::moderation()
    ->using('openai', 'omni-moderation-latest')
    ->fromInput([
        'First piece of content',
        'Second piece of content',
        'Third piece of content',
    ])
    ->asModeration();

if ($response->isFlagged()) {
    foreach ($response->flagged() as $flaggedResult) {
        // Handle flagged content
    }
}
```

## Example: Comment Moderation

```php
class CommentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $moderation = Atlas::moderation()
            ->using('openai', 'omni-moderation-latest')
            ->fromInput($validated['body'])
            ->asModeration();

        if ($moderation->isFlagged()) {
            return back()->withErrors([
                'body' => 'Your comment violates our community guidelines.',
            ]);
        }

        Comment::create([
            'user_id' => auth()->id(),
            'body' => $validated['body'],
        ]);

        return redirect()->back();
    }
}
```

## Pipeline Hooks

Moderation supports pipeline middleware for observability:

<div class="full-width-table">

| Pipeline | Trigger |
|----------|---------|
| `moderation.before_moderation` | Before content moderation |
| `moderation.after_moderation` | After moderation completes |

</div>

```php
use Atlasphp\Atlas\Contracts\PipelineContract;

class LogFlaggedContent implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        $result = $next($data);

        if ($result['response']->isFlagged()) {
            Log::warning('Content flagged', [
                'user_id' => $data['metadata']['user_id'] ?? null,
            ]);
        }

        return $result;
    }
}

$registry->register('moderation.after_moderation', LogFlaggedContent::class);
```

## Next Steps

- [Prism Moderation](https://prismphp.com/core-concepts/moderation.html) — Complete moderation reference
- [Pipelines](/core-concepts/pipelines) — Add logging and metrics
