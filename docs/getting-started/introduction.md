# Introduction

Atlas is a Laravel package for building AI-powered applications with structure and scale. It provides reusable agents, typed tools, system prompt templating, and execution pipelines—all through a clean, stateless API.

Built on [Prism PHP](https://prismphp.com), Atlas lets you focus on application logic instead of wiring AI infrastructure.

## Quick Example

```php
$response = Atlas::agent('support-agent')->chat('I need help with my order');
```

## What Atlas Provides

Atlas is built on [Prism PHP](https://prismphp.com) and handles **application-level AI concerns** while Prism handles **LLM communication**. You can always use Prism directly—Atlas provides convenience and structure, not limitations.

<div class="full-width-table">

| Feature | Description |
|---------|-------------|
| **Agent Registry** | Define agents once, use anywhere by key, class, or instance |
| **Tool Registry** | Connect agents to your business services with typed parameters |
| **Dynamic Prompts** | Variables like `{user_name}` interpolate at runtime |
| **Pipelines** | Extend Atlas for logging, auth, metrics—without coupling |
| **Multi-Provider** | OpenAI, Anthropic, others. Swap via config |

</div>

::: tip Full Prism Access
Any Prism method works through Atlas:
```php
Atlas::agent('support')
    ->withMaxTokens(1000)     // Prism method
    ->withTemperature(0.7)    // Prism method
    ->chat('Hello');          // Atlas terminal
```
:::

## Design Philosophy

Atlas is intentionally stateless. Your application manages all persistence (conversations, user context, etc.) and passes data via execution context. This gives you:

- Full control over conversation history and storage
- Freedom to implement custom trimming, summarization, or replay logic
- Clean separation between AI execution and your application state

## Next Steps

- [Installation](/getting-started/installation) — Get Atlas set up in your Laravel app
- [Configuration](/getting-started/configuration) — Configure providers and defaults
