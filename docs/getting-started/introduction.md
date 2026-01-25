# Introduction

Atlas is an organizational layer for [Prism PHP](https://prismphp.com) that adds structure for building production AI applications in Laravel. It provides reusable agents, typed tools, dynamic prompts, and execution pipelines without hiding or replacing Prism.

**Atlas returns Prism responses directly.** You get the same `PrismResponse` objects with full access to `->text`, `->usage`, `->toolCalls`, `->steps`, and everything else Prism provides.

## Quick Example

```php
// Define once
class SupportAgent extends AgentDefinition
{
    public function provider(): ?string { return 'anthropic'; }
    public function model(): ?string { return 'claude-sonnet-4-20250514'; }
    public function systemPrompt(): ?string { return 'You help {user_name} with support issues.'; }
    public function tools(): array { return [LookupOrderTool::class]; }
}

// Use anywhere
$response = Atlas::agent(SupportAgent::class)
    ->withVariables(['user_name' => 'Sarah'])
    ->chat('I need help with my order');

$response->text;   // Prism response - full access
$response->usage;  // Token usage, cache stats, etc.
```

## Full Prism Compatibility

Atlas doesn't hide Prism. It organizes access to it. Everything you can do with Prism works through Atlas:

```php
// All Prism fluent methods work
Atlas::agent('support')
    ->withMaxTokens(2000)           // Prism method
    ->usingTemperature(0.7)         // Prism method
    ->withClientOptions([...])      // Prism method
    ->withProviderOptions([...])    // Prism method
    ->chat('Hello');

// Direct Prism access with pipeline hooks
Atlas::text()
    ->using('openai', 'gpt-4o')
    ->withPrompt('Explain quantum computing')
    ->asText();  // Returns Prism Response directly

// Or use Prism directly, Atlas doesn't interfere
Prism::text()->using('openai', 'gpt-4o')->withPrompt('...')->asText();
```

::: tip Prism Passthrough
When you call `Atlas::text()`, `Atlas::embeddings()`, `Atlas::image()`, etc., you get a thin proxy that adds pipeline hooks around Prism's terminal methods. All fluent methods pass through unchanged to Prism.
:::

## What Atlas Adds

<div class="full-width-table">

| Feature | What It Does |
|---------|--------------|
| **Agent Definitions** | Encapsulate provider, model, prompt, tools, and options in reusable classes |
| **Tool Definitions** | Typed tool classes with parameter schemas and execution context |
| **System Prompt Variables** | `{user_name}`, `{context}` interpolation with runtime values |
| **Pipeline Middleware** | Before/after hooks for logging, auth, metrics, error recovery |
| **Agent Decorators** | Modify agent behavior at runtime without changing classes |
| **Conditional Pipelines** | Run handlers only when conditions match (premium users, specific agents) |
| **Auto-Discovery** | Agents and tools auto-register from configured directories |
| **Testing Utilities** | Fake responses, assert requests, test tool execution |

</div>

## Design Philosophy

Atlas is intentionally **stateless**. Your application manages all persistence (conversations, user context, etc.) and passes data via execution context:

```php
// You control the history
$messages = $this->loadConversationHistory($userId);

$response = Atlas::agent('support')
    ->withMessages($messages)
    ->withMetadata(['user_id' => $userId])
    ->chat($newMessage);

// You control persistence
$this->saveMessage($userId, $response->text);
```

This gives you full control over conversation storage, trimming, summarization, and replay logic.

## Next Steps

- [Installation](/getting-started/installation) — Get Atlas set up in your Laravel app
- [Configuration](/getting-started/configuration) — Configure providers and defaults
- [Agents](/core-concepts/agents) — Define reusable AI configurations
- [Tools](/core-concepts/tools) — Add callable tools to agents
- [Pipelines](/core-concepts/pipelines) — Add middleware for logging, auth, and metrics
