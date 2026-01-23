# Moderation

Content moderation capabilities for detecting potentially harmful or inappropriate content using AI-powered analysis.

## Basic Usage

Check content for policy violations:

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;

$result = Atlas::moderation()->moderate('Text to check for violations');

if ($result->isFlagged()) {
    // Content was flagged - take appropriate action
    $flaggedCategories = $result->firstFlagged()->flaggedCategories();
}
```

## Provider Support

| Provider | Supported | Notes |
|----------|-----------|-------|
| OpenAI | Yes | Full support via `omni-moderation-latest` |
| Anthropic | No | Not supported |
| Gemini | No | Not supported |
| Ollama | No | Not supported |

::: warning
Currently only OpenAI supports content moderation. Using other providers will throw an unsupported exception.
:::

## Checking Results

### Single Input

```php
$result = Atlas::moderation()->moderate('Content to analyze');

// Check if content was flagged
if ($result->isFlagged()) {
    echo 'Content violates policies';
}

// Get aggregated categories
$categories = $result->categories();
// ['violence' => false, 'hate' => true, 'sexual' => false, ...]

// Get category scores (0.0 to 1.0)
$scores = $result->categoryScores();
// ['violence' => 0.01, 'hate' => 0.95, 'sexual' => 0.02, ...]
```

### Batch Moderation

Moderate multiple inputs in a single request:

```php
$result = Atlas::moderation()->moderate([
    'First piece of content',
    'Second piece of content',
    'Third piece of content',
]);

// Check if any content was flagged
if ($result->isFlagged()) {
    // Get all flagged results
    foreach ($result->flagged() as $flaggedResult) {
        $categories = $flaggedResult->flaggedCategories();
    }
}

// Iterate through all results
foreach ($result->results as $index => $moderationResult) {
    if ($moderationResult->flagged) {
        echo "Content #{$index} was flagged";
    }
}
```

## Response Objects

### ModerationResponse

The response from a moderation request:

```php
$result = Atlas::moderation()->moderate($content);

$result->isFlagged();         // bool - true if any result is flagged
$result->firstFlagged();      // ?ModerationResult - first flagged result
$result->flagged();           // ModerationResult[] - all flagged results
$result->categories();        // array<string, bool> - aggregated categories
$result->categoryScores();    // array<string, float> - max scores per category
$result->results;             // ModerationResult[] - all results
$result->id;                  // string - API response ID
$result->model;               // string - model used
```

### ModerationResult

Individual result for each moderated input:

```php
$singleResult = $result->results[0];

$singleResult->flagged;                          // bool
$singleResult->categories;                       // array<string, bool>
$singleResult->categoryScores;                   // array<string, float>
$singleResult->flaggedCategories();              // string[] - only flagged category names
$singleResult->isCategoryFlagged('violence');    // bool
$singleResult->getCategoryScore('violence');     // ?float
```

## Categories

OpenAI's moderation API checks for the following categories:

| Category | Description |
|----------|-------------|
| `hate` | Content expressing hatred toward a group |
| `hate/threatening` | Hateful content with threats of violence |
| `harassment` | Content that harasses a target |
| `harassment/threatening` | Harassment with threats |
| `self-harm` | Content promoting self-harm |
| `self-harm/intent` | Expression of intent to self-harm |
| `self-harm/instructions` | Instructions for self-harm |
| `sexual` | Sexual content |
| `sexual/minors` | Sexual content involving minors |
| `violence` | Violent content |
| `violence/graphic` | Graphic violent content |

## Configuration

### Provider Override

```php
// Specify provider and model
$result = Atlas::moderation('openai', 'omni-moderation-latest')
    ->moderate('Content to check');

// Or use fluent API
$result = Atlas::moderation()
    ->withProvider('openai', 'omni-moderation-latest')
    ->moderate('Content to check');

// Override just the model
$result = Atlas::moderation()
    ->withModel('text-moderation-latest')
    ->moderate('Content to check');
```

### Available Models

| Model | Description |
|-------|-------------|
| `omni-moderation-latest` | Latest multimodal moderation (default) |
| `text-moderation-latest` | Latest text-only moderation |
| `text-moderation-stable` | Stable text-only moderation |

### Default Configuration

Configure defaults in `config/atlas.php`:

```php
'moderation' => [
    'provider' => env('ATLAS_MODERATION_PROVIDER', 'openai'),
    'model' => env('ATLAS_MODERATION_MODEL', 'omni-moderation-latest'),
],
```

## Metadata for Pipelines

Pass metadata for logging and observability:

```php
$result = Atlas::moderation()
    ->withMetadata([
        'user_id' => auth()->id(),
        'content_type' => 'comment',
        'source' => 'web_form',
    ])
    ->moderate($userContent);
```

## Retry Configuration

Handle transient failures with automatic retries:

```php
// Simple retry: 3 attempts, 1 second delay
$result = Atlas::moderation()
    ->withRetry(3, 1000)
    ->moderate($content);

// Exponential backoff
$result = Atlas::moderation()
    ->withRetry(3, fn($attempt) => (2 ** $attempt) * 100)
    ->moderate($content);

// Only retry on specific conditions
$result = Atlas::moderation()
    ->withRetry(3, 1000, fn($e) => $e->getCode() === 429)
    ->moderate($content);
```

## Pipelines

Atlas provides pipeline hooks for moderation operations:

| Pipeline | Description |
|----------|-------------|
| `moderation.before_moderate` | Before content moderation |
| `moderation.after_moderate` | After moderation completes |
| `moderation.on_error` | When moderation fails |

### Pipeline Context

**before_moderate:**
```php
[
    'input' => string|array,    // Content being moderated
    'provider' => string,       // Provider name
    'model' => string,          // Model name
    'options' => array,         // Request options
    'metadata' => array,        // User metadata
]
```

**after_moderate:**
```php
[
    'input' => string|array,
    'provider' => string,
    'model' => string,
    'options' => array,
    'metadata' => array,
    'result' => ModerationResponse,  // The moderation result
]
```

**on_error:**
```php
[
    'input' => string|array,
    'provider' => string,
    'model' => string,
    'metadata' => array,
    'exception' => Throwable,
]
```

### Example: Logging Pipeline

```php
use Atlasphp\Atlas\Foundation\Services\PipelineRegistry;

$registry = app(PipelineRegistry::class);

$registry->register('moderation.after_moderate', function ($data, $next) {
    if ($data['result']->isFlagged()) {
        Log::warning('Content flagged', [
            'user_id' => $data['metadata']['user_id'] ?? null,
            'categories' => $data['result']->firstFlagged()->flaggedCategories(),
        ]);
    }

    return $next($data);
});
```

## Use Cases

### Comment Moderation

```php
class CommentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        // Check content before storing
        $moderation = Atlas::moderation()
            ->withMetadata(['user_id' => auth()->id()])
            ->moderate($validated['body']);

        if ($moderation->isFlagged()) {
            return back()->withErrors([
                'body' => 'Your comment contains content that violates our community guidelines.',
            ]);
        }

        Comment::create([
            'user_id' => auth()->id(),
            'body' => $validated['body'],
        ]);

        return redirect()->back()->with('success', 'Comment posted!');
    }
}
```

### Batch Content Review

```php
class ContentModerationJob implements ShouldQueue
{
    public function handle(): void
    {
        $pendingPosts = Post::where('moderation_status', 'pending')
            ->take(100)
            ->get();

        $contents = $pendingPosts->pluck('content')->toArray();

        $results = Atlas::moderation()
            ->withMetadata(['batch_job' => true])
            ->moderate($contents);

        foreach ($pendingPosts as $index => $post) {
            $result = $results->results[$index];

            $post->update([
                'moderation_status' => $result->flagged ? 'rejected' : 'approved',
                'moderation_categories' => $result->flagged
                    ? $result->flaggedCategories()
                    : null,
            ]);
        }
    }
}
```

### Chat Message Filter

```php
class ChatService
{
    public function sendMessage(User $user, string $message, Channel $channel): Message
    {
        $moderation = Atlas::moderation()
            ->withMetadata([
                'user_id' => $user->id,
                'channel_id' => $channel->id,
            ])
            ->moderate($message);

        if ($moderation->isFlagged()) {
            $flaggedResult = $moderation->firstFlagged();

            // Log the violation
            ModerationLog::create([
                'user_id' => $user->id,
                'content' => $message,
                'categories' => $flaggedResult->flaggedCategories(),
                'scores' => $flaggedResult->categoryScores,
            ]);

            // Check severity
            if ($flaggedResult->isCategoryFlagged('violence/graphic') ||
                $flaggedResult->isCategoryFlagged('sexual/minors')) {
                // Severe violation - immediate action
                $user->suspend();
                throw new ContentViolationException('Severe policy violation');
            }

            throw new ContentViolationException('Message violates community guidelines');
        }

        return $channel->messages()->create([
            'user_id' => $user->id,
            'content' => $message,
        ]);
    }
}
```

### User Profile Validation

```php
class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'bio' => 'nullable|string|max:500',
            'display_name' => 'nullable|string|max:50',
        ]);

        // Moderate all text fields together
        $fieldsToCheck = array_filter([
            $validated['bio'],
            $validated['display_name'],
        ]);

        if (!empty($fieldsToCheck)) {
            $moderation = Atlas::moderation()->moderate($fieldsToCheck);

            if ($moderation->isFlagged()) {
                return back()->withErrors([
                    'profile' => 'Your profile contains inappropriate content.',
                ]);
            }
        }

        auth()->user()->update($validated);

        return redirect()->back()->with('success', 'Profile updated!');
    }
}
```

## PendingModerationRequest Methods

| Method | Description |
|--------|-------------|
| `withProvider(string $provider, ?string $model = null)` | Set provider and optionally model |
| `withModel(string $model)` | Set moderation model |
| `withProviderOptions(array $options)` | Set provider-specific options |
| `withMetadata(array $metadata)` | Set metadata for pipelines |
| `withRetry($times, $delay, $when, $throw)` | Configure retry behavior |
| `moderate(string\|array $input, array $options = [])` | Moderate content |

## Best Practices

### 1. Moderate Before Storing

Always moderate user content before persisting:

```php
// Good - moderate first
$moderation = Atlas::moderation()->moderate($content);
if (!$moderation->isFlagged()) {
    Post::create(['content' => $content]);
}

// Bad - storing then checking
$post = Post::create(['content' => $content]);
$moderation = Atlas::moderation()->moderate($content);
```

### 2. Use Batch for Efficiency

When moderating multiple items, use batch moderation:

```php
// Good - single API call
$result = Atlas::moderation()->moderate([$text1, $text2, $text3]);

// Less efficient - multiple API calls
$result1 = Atlas::moderation()->moderate($text1);
$result2 = Atlas::moderation()->moderate($text2);
$result3 = Atlas::moderation()->moderate($text3);
```

### 3. Handle Edge Cases

Consider content that might be incorrectly flagged:

```php
$result = Atlas::moderation()->moderate($content);

if ($result->isFlagged()) {
    $firstFlagged = $result->firstFlagged();
    $maxScore = max($firstFlagged->categoryScores);

    // Only reject high-confidence flags
    if ($maxScore > 0.8) {
        // Definitely problematic
        return $this->reject($content);
    } else {
        // Queue for human review
        return $this->queueForReview($content, $firstFlagged);
    }
}
```

### 4. Log Moderation Decisions

Track moderation for audit and improvement:

```php
$result = Atlas::moderation()
    ->withMetadata(['tracking_id' => Str::uuid()])
    ->moderate($content);

ModerationAudit::create([
    'content_hash' => hash('sha256', $content),
    'flagged' => $result->isFlagged(),
    'categories' => $result->categories(),
    'scores' => $result->categoryScores(),
    'model' => $result->model,
]);
```

## API Summary

```php
// Basic moderation
Atlas::moderation()->moderate('Text to check');

// With provider/model
Atlas::moderation('openai', 'omni-moderation-latest')->moderate($text);

// Batch moderation
Atlas::moderation()->moderate(['Text 1', 'Text 2', 'Text 3']);

// Full configuration
Atlas::moderation()
    ->withProvider('openai')
    ->withModel('omni-moderation-latest')
    ->withMetadata(['user_id' => 123])
    ->withRetry(3, 1000)
    ->moderate($content);
```

## Next Steps

- [Configuration](/getting-started/configuration) — Configure moderation providers
- [Pipelines](/core-concepts/pipelines) — Add logging and metrics
- [Chat](/capabilities/chat) — Combine with chat for safe conversations
