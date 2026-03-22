# Introduction

Atlas is a unified AI execution layer for Laravel. It owns its own provider layer — no external AI package dependency. Atlas talks directly to AI provider APIs, manages the tool call loop, and provides optional persistence for conversations, execution tracking, and agent memory.

## Quick Example

```php
// Define once
class SupportAgent extends Agent
{
    public function provider(): ?string { return 'anthropic'; }
    public function model(): ?string { return 'claude-sonnet-4-20250514'; }
    public function instructions(): ?string { return 'You help {user_name} with support issues.'; }
    public function tools(): array { return [LookupOrderTool::class]; }
}

// Use anywhere
$response = Atlas::agent('support')
    ->withVariables(['user_name' => 'Sarah'])
    ->message('I need help with my order')
    ->asText();

$response->text;       // Generated text
$response->usage;      // Token usage
$response->steps;      // Tool call loop history
```

## What Atlas Provides

<div class="full-width-table">

| Feature | What It Does |
|---------|--------------|
| **Agent Definitions** | Reusable classes encapsulating provider, model, instructions, tools |
| **Tool Definitions** | Typed tool classes with parameter schemas and dependency injection |
| **Variable Interpolation** | `{variable}` placeholders in instructions with runtime values |
| **Middleware Layers** | Agent, step, tool, and provider middleware for observability and control |
| **Lifecycle Events** | 34 events across all execution boundaries |
| **Persistence** | Optional conversations, execution tracking, and agent memory |
| **Queue Support** | Async execution with real-time broadcasting |
| **Multi-Modal** | Text, images, audio, video, embeddings, moderation, reranking |

</div>

## Multi-Modal

Atlas provides dedicated request builders for each modality:

```php
// Text generation
$response = Atlas::text('openai', 'gpt-4o')->message('Hello')->asText();

// Image generation
$image = Atlas::image('openai', 'dall-e-3')->message('A mountain sunset')->asImage();

// Streaming
$stream = Atlas::text('openai', 'gpt-4o')->message('Tell me a story')->asStream();
```

::: tip All Modalities
Beyond text, images, and streaming, Atlas supports `audio()`, `video()`, `embed()`, `moderate()`, and `rerank()` — each with its own request builder and response type.
:::

## Design Philosophy

Atlas v3 is **batteries included, batteries optional**:

- **Works stateless out of the box.** Send a message, get a response. No database tables required.
- **Enable persistence when you need it.** Conversations, execution tracking, and agent memory are opt-in.
- **Middleware for custom observability.** Four layers (agent, step, tool, provider) let you hook into every phase of execution.
- **Events for reactive integrations.** 34 lifecycle events for logging, metrics, webhooks, or any side effect.

## Next Steps

- [Installation](/getting-started/installation) — Get Atlas set up in your Laravel app
- [Configuration](/getting-started/configuration) — Configure providers and defaults
- [Agents](/core-concepts/agents) — Define reusable AI agent classes
- [Tools](/core-concepts/tools) — Add callable tools to agents
