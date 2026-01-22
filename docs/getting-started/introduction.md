# Introduction

Atlas is a Laravel package for building AI-powered applications with structure and scale. It provides reusable agents, typed tools, system prompt templating, and execution pipelines—all through a clean, stateless API.

Built on [Prism PHP](https://github.com/prism-php/prism), Atlas lets you focus on application logic instead of wiring AI infrastructure.

## Quick Example

```php
$response = Atlas::chat('support-agent', 'I need help with my order');
```

## What Atlas Provides

Atlas handles **application-level AI concerns** while Prism handles **LLM communication**.

| Feature | Description |
|---------|-------------|
| **Agent Registry** | Define agents once, use anywhere by key, class, or instance |
| **Tool Registry** | Connect agents to your business services with typed parameters |
| **Dynamic Prompts** | Variables like `{user_name}` interpolate at runtime |
| **Pipelines** | Extend Atlas for logging, auth, metrics—without coupling |
| **Multi-Provider** | OpenAI, Anthropic, others. Swap via config |

## Why Atlas?

- **Build reusable, composable agents** — not one-off prompts
- **Keep AI logic stateless, testable, and framework-native**
- **Extend behavior (logging, auth, metrics) without touching the core**

## Beyond Chat

Atlas provides more than just chat capabilities:

- [Embeddings](/capabilities/embeddings) — Vector embeddings for semantic search and RAG
- [Image Generation](/capabilities/images) — Generate images with DALL-E and other providers
- [Speech](/capabilities/speech) — Text-to-speech and speech-to-text services

## Design Philosophy

Atlas is intentionally stateless. Your application manages all persistence (conversations, user context, etc.) and passes data via execution context. This gives you:

- Full control over conversation history and storage
- Freedom to implement custom trimming, summarization, or replay logic
- Clean separation between AI execution and your application state

## Note from the Author

> Atlas has been built through deliberate iteration over the past year. This RC4 release reflects a stable, battle-tested core already running in large-scale production. Atlas is intentionally stateless, with persistence and orchestration planned for Nexus, a companion package in active development. Feedback and issues are always welcome.
>
> — TM

## Requirements

- PHP 8.4+
- Laravel 12.x

## Next Steps

- [Installation](/getting-started/installation) — Get Atlas set up in your Laravel app
- [Configuration](/getting-started/configuration) — Configure providers and defaults
