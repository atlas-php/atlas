# Token Counting

Count the **input tokens** a request would consume **before** you send it — for free. Use it to estimate cost up front, reject context that won't fit the model's window, or enforce a per-user / per-tenant token budget.

```php
$count = Atlas::text('anthropic', 'claude-sonnet-4-5')
    ->instructions('You are a helpful assistant.')
    ->message('Summarize the attached report.')
    ->countTokens();

$count->inputTokens; // 1287
```

`countTokens()` runs **only when you call it**. It never fires as a side effect of generation, and it skips the middleware stack and tool loop — it is a single, cheap, read-only pre-flight call.

## Why this beats a local tokenizer

A local `strlen / 4` (or even a real BPE tokenizer) can only see plain text. It cannot count the tokens added by **images, PDFs, or tool schemas** — and those are often the largest part of a request. Atlas counts the **exact payload it would send** by asking the provider's own server-side endpoint, so the number includes the system prompt, every tool definition, and all attached media.

```php
// Counts the system prompt, the tool's JSON schema, and the image — all of it.
$count = Atlas::text('openai', 'gpt-4o')
    ->instructions($largeSystemPrompt)
    ->withTools([WeatherTool::class])
    ->message('What should I wear?', Image::fromPath('outfit.jpg'))
    ->countTokens();
```

It works on agents too — the agent's instructions, model, and tools are resolved exactly as a real turn would be:

```php
$count = Atlas::agent('support')
    ->message('My order never arrived.')
    ->countTokens();
```

## The `TokenCount` object

| Property | Type | Description |
|----------|------|-------------|
| `inputTokens` | `int` | Input tokens the request would consume |
| `estimated` | `bool` | `false` for an exact provider count, `true` for a heuristic estimate |
| `provider` | `string` | Provider the count is for |
| `model` | `string` | Model the count is for |
| `breakdown` | `array<string, int>` | Optional per-category detail (e.g. `['cached_tokens' => 1200]` on Google) |

Output tokens are **not** counted — they are unknowable until the model responds. This is a pre-flight *input* count.

## Provider support

| Provider | Source | `estimated` |
|----------|--------|:-----------:|
| **Anthropic** | `POST /v1/messages/count_tokens` (native) | `false` |
| **OpenAI** | `POST /v1/responses/input_tokens` (native) | `false` |
| **Google** | `…/{model}:countTokens` (native) | `false` |
| **xAI** | heuristic estimate | `true` |
| **Ollama / LM Studio / custom** | heuristic estimate | `true` |

The native endpoints are exact (verified against the billed `usage->inputTokens`) and free, subject to each provider's own rate limit. Providers without a full-request count endpoint fall back to the `chars / 4` heuristic — always check the `estimated` flag if you need to know which you got. The heuristic walks the built payload, so base64-encoded media inflates the figure; treat estimated counts as approximate.

## Quick local estimate

Need a rough number for a raw string — with no provider, no model, and no network call? Use the `TokenCounter` utility directly. It's the same `chars / 4` heuristic Atlas uses for chunk sizing and for providers without a native endpoint:

```php
use Atlasphp\Atlas\Support\TokenCounter;

TokenCounter::count($text); // e.g. 74 — instant, offline, model-agnostic
```

This is approximate and counts a plain string only (no message wrapper, tools, or media). Reach for it when you just want a fast sanity check; use `countTokens()` when you need a model-accurate number for an actual request.

## Estimating cost

Atlas does **not** ship a price table — model prices change and a stale one is worse than none. Once you have a token count, apply your own current per-model rates:

```php
$count = Atlas::text('openai', 'gpt-4o')->message($prompt)->countTokens();

$estimatedInputCost = $count->inputTokens * $yourInputRatePerToken;
```

## Enforcing a budget

Atlas ships token counting, not a budget system — that way you stay in control of the policy. There are two clean ways to enforce a cap, both token-based so no stale pricing is involved.

### Pre-flight gate

Reject a request before it is ever sent:

```php
$count = Atlas::text('anthropic', 'claude-sonnet-4-5')
    ->message($userInput)
    ->countTokens();

if ($count->inputTokens > $tenant->remainingTokenBudget()) {
    throw new BudgetExceededException("Request would exceed the remaining token budget.");
}

$response = Atlas::text('anthropic', 'claude-sonnet-4-5')
    ->message($userInput)
    ->asText();
```

### Cumulative cap via middleware

For agents and tool loops, enforce a running cap with a [step middleware](/features/middleware) that reads the accumulated usage and aborts the loop when it crosses your limit. Define your own exception and budget source:

```php
use Atlasphp\Atlas\Middleware\Contracts\StepMiddleware;
use Atlasphp\Atlas\Middleware\StepContext;
use Closure;

class EnforceTokenBudget implements StepMiddleware
{
    public function __construct(private int $maxTokens) {}

    public function handle(StepContext $context, Closure $next): mixed
    {
        if ($context->accumulatedUsage->totalTokens() > $this->maxTokens) {
            throw new \App\Exceptions\BudgetExceededException(
                "Token budget of {$this->maxTokens} exceeded for {$context->agentKey}."
            );
        }

        return $next($context);
    }
}
```

Register it globally or per request — see [Middleware](/features/middleware) for registration and the full `StepContext` surface (`accumulatedUsage`, `stepNumber`, `meta`, `agentKey`). Combine the pre-flight gate (stop oversized requests up front) with the cumulative cap (stop runaway tool loops) for full coverage.

## Checking the billed count

After a call, the actual billed tokens are on the response's [`Usage`](/modalities/text) object — compare it to your pre-flight count to calibrate:

```php
$response = Atlas::text('google', 'gemini-2.5-flash')->message($prompt)->asText();
$response->usage->inputTokens; // billed input tokens
```
