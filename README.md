<p align="center">
  <a href="https://atlasphp.org">
    <img src="./images/atlas-logo-3.png" alt="Atlas logo" height="180">
  </a>
</p>
<p align="center">
    <a href="https://github.com/atlas-php/atlas/actions"><img src="https://github.com/atlas-php/atlas/workflows/Automated%20Tests/badge.svg" alt="Automated Tests"></a>
    <a href="https://codecov.io/gh/atlas-php/atlas/branch/3.x"><img src="https://codecov.io/gh/atlas-php/atlas/branch/3.x/graph/badge.svg" alt="Code Coverage"></a>
    <a href="https://packagist.org/packages/atlas-php/atlas"><img src="https://img.shields.io/packagist/dt/atlas-php/atlas.svg?style=flat-square" alt="Total Downloads"></a>
    <img src="https://img.shields.io/badge/php-8.2%2B-blue?style=flat-square" alt="PHP Version">
    <img src="https://img.shields.io/badge/laravel-11%2B-orange?style=flat-square" alt="Laravel">
    <img src="https://img.shields.io/badge/license-MIT-green?style=flat-square" alt="License">
</p>
<p align="center">
    ✨ <a href="#-features"><strong>Features</strong></a>
    &nbsp;·&nbsp;
    📚 <a href="https://atlasphp.org"><strong>Documentation</strong></a>
    &nbsp;·&nbsp;
    🧪 <a href="#-sandbox"><strong>Sandbox &amp; Examples</strong></a>
    &nbsp;·&nbsp;
    ⚖️ <a href="./COMPARISON.md"><strong>Comparison</strong></a>
</p>

# 🪐 Atlas

**A unified AI SDK for Laravel — agents, tools, and 10 modalities across every major provider.**

One API for OpenAI, Anthropic, Google, xAI, ElevenLabs, and any OpenAI-compatible endpoint — with no external AI dependency. Atlas runs the tool-call loop and adds optional persistence for conversations, tracking, and memory.

> Every provider modality is **live-tested against real provider APIs**. See the [API Audit](./AUDIT.md).

## ⚡ It's this simple

```php
use Atlasphp\Atlas\Atlas;

$response = Atlas::text('openai', 'gpt-4o-mini')
    ->message('Draft a brief, friendly email letting a client know their project is running two days behind, and propose Thursday for delivery.')
    ->asText();

echo $response->text;
//  Hi Jordan — a quick heads-up that we're running about two days behind
//  on the project. We're aiming to have everything to you by Thursday..."
```

No agent classes, no config. Add `->instructions()` and `->withTools()` when you need them, or graduate to a reusable [Agent](#define-an-agent) as your app grows.

## 🎯 Highlights

- **Every modality** — text, images, audio, music, SFX, video, realtime voice, embeddings, reranking, moderation. Voice, music, SFX, and video are Atlas-only.
- **A real agent framework** — first-class agents, sub-agents with guards and true parallel fan-out, auditable execution trees.
- **Production-grade persistence** — conversation memory, execution tracking, retry & branch, media assets on disk (S3/local).
- **Operational services** — pre-flight token counting, provider-call observability, runtime model/voice discovery.
- **Control at every layer** — middleware across agent, step, tool, and provider boundaries.
- **Multi-provider, one API** — every major provider plus any OpenAI-compatible endpoint; swap by changing a string.

## 💡 Why Atlas?

Plenty of AI libraries get you a response and stop there. Atlas handles everything after the happy path — the retries, errors, and tracking real apps run into.

- **Tested for real, not mocked.** Every modality runs against the live provider API ([API Audit](./AUDIT.md)), and every feature ships with automated tests. What the docs say is what ships.
- **Production-hardened.** Automatic retries, typed exceptions, observability, and cost tracking that survives interrupted streams — the failure modes real apps hit are already handled.
- **Keeps pace with the providers.** New models, tools, and capabilities land fast — the [CHANGELOG](./CHANGELOG.md) shows the cadence.

And where it counts, Atlas simply does more: realtime voice, video, retry & branch, and pre-flight token counting are all Atlas-only among Laravel AI libraries. **[See the full comparison →](./COMPARISON.md)**

## 🚀 Quick Start

```bash
composer require atlas-php/atlas
```

Supports Laravel 11+.

```bash
php artisan vendor:publish --tag=atlas-config
```

### Define an Agent

```php
use Atlasphp\Atlas\Agent;

class PlantShopAgent extends Agent
{
    public function provider(): ?string
    {
        return 'anthropic';
    }

    public function model(): ?string
    {
        return 'claude-sonnet-4-20250514';
    }

    public function instructions(): ?string
    {
        return <<<'PROMPT'
        You are Fern, the friendly plant-care and order specialist for {shop_name}.

        ## Customer
        - **Name:** {customer_name}
        - **Member since:** {member_since}

        ## How you help
        - Greet {customer_name} by name and keep it warm.
        - For anything about an order, call `lookup_order` first — never guess a status.
        - If a plant arrived damaged, use `start_return` to open a replacement, then reassure them.
        - Drop in a genuinely useful care tip when it fits.
        PROMPT;
    }

    public function tools(): array
    {
        return [
            LookupOrderTool::class,
            StartReturnTool::class,
        ];
    }
}
```

### Build a Tool

```php
use Atlasphp\Atlas\Tools\Tool;
use Atlasphp\Atlas\Schema\Fields\StringField;

class LookupOrderTool extends Tool
{
    public function __construct(
        private OrderService $orders
    ) {}

    public function name(): string
    {
        return 'lookup_order';
    }

    public function description(): string
    {
        return 'Look up the status and contents of an order by its ID';
    }

    public function parameters(): array
    {
        return [
            new StringField('order_id', 'The order ID, e.g. "5512"'),
        ];
    }

    public function handle(array $args, array $context): mixed
    {
        $order = $this->orders->find($args['order_id']);

        return $order ? $order->toArray() : 'No order found with that ID.';
    }
}
```

### Chat with the Agent

```php
$response = Atlas::agent('plant-shop')
    ->withVariables([
        'shop_name' => 'Leaf & Co.',
        'customer_name' => 'Sarah',
        'member_since' => '2023',
    ])
    ->message('My monstera arrived with a snapped leaf — order #5512. Can I get a replacement?')
    ->asText();

$response->text;    // "Oh no, Sarah! Let me pull up #5512 for you..."
$response->usage;   // Token usage
$response->steps;   // Tool call loop — lookup_order, then start_return
```

### Speak with the Agent (Voice to Voice)

```php
$session = Atlas::agent('plant-shop')
    ->withVariables([
        'shop_name' => 'Leaf & Co.',
        'customer_name' => 'Sarah',
        'member_since' => '2023',
    ])
    ->asVoice();

return response()->json($session->toClientPayload());
// Returns ephemeral token + connection URL for WebRTC/WebSocket
```

See the [Voice Integration Guide](https://atlasphp.org/guides/voice-integration/) for full setup instructions.

### Optional: Enable Persistence

Atlas runs fully stateless by default — no database required. To unlock conversation history, execution tracking, media-asset storage, and retry & branch, publish and run the migrations:

```bash
php artisan vendor:publish --tag=atlas-migrations
php artisan migrate
```

Then turn it on in `.env`:

```env
ATLAS_PERSISTENCE_ENABLED=true
```

That's it — `->for($user)` and `->forConversation($id)` now load and persist history automatically.

## ✨ Features

### Core generation

- **Text & structured output** — schema-validated JSON from any provider
- **Streaming** — SSE + Laravel Broadcasting, real-time chunks
- **Tool calling** — typed tools, multi-step loop, concurrent execution
- **Reasoning** — configure, stream, and persist extended thinking
- **Prompt caching** — cache long system prompts to cut cost
- **Token counting** — count input (with tools & images) before sending

### Agent framework

- **Agents** — provider, model, instructions, and tools in one reusable class
- **Sub-agents** — delegation with depth/cycle guards, lineage, and parallel fan-out
- **Conversations & memory** — multi-turn history, retry & branch, agent memory
- **Execution tracking** — steps, tools, usage, and assets persisted
- **Media assets** — auto-stored to disk (S3/local), linked to messages
- **Middleware** — agent, step, tool, and provider layers
- **Variable interpolation** — `{var}` placeholders resolved at runtime
- **Queues** — async execution with broadcasting and callbacks

### Modalities

- **10 modalities** — text, images, audio, music, sound effects, video, voice, embeddings, reranking, moderation
- **Realtime voice** — bidirectional voice conversations with tools
- **Similarity search** — whole-record or chunked embeddings, diff-based re-embedding

### Ecosystem & tooling

- **Provider tools** — web search, code interpreter, file search
- **MCP** — composes with [Laravel MCP](https://github.com/laravel/mcp)
- **Provider discovery** — list models/voices, validate keys, inspect capabilities
- **Observability** — trace every provider call, correlation IDs across retries
- **Testing** — full per-modality fakes, no API keys required
- **Custom providers** — OpenAI-compatible endpoints or fully custom drivers

## 🔌 Providers

Atlas talks to every major provider through one interface. Switch by changing a string — your agents, tools, and middleware stay the same.

**First-party drivers**

OpenAI · Anthropic · Google (Gemini) · xAI (Grok) · ElevenLabs · Cohere · Jina

**Any OpenAI-compatible API**

Ollama · Groq · DeepSeek · Together · OpenRouter · LM Studio · Mistral · Perplexity

## 📖 Documentation

**[atlasphp.org](https://atlasphp.org)** — Full guides, API reference, and examples.

- [Getting Started](https://atlasphp.org/getting-started/installation/) — Installation and configuration
- [Agents](https://atlasphp.org/features/agents/) — Define reusable AI configurations
- [Tools](https://atlasphp.org/features/tools/) — Connect agents to your application
- [Sub-agents](https://atlasphp.org/features/sub-agents/) — Agent-to-agent delegation, including concurrent (parallel) fan-out
- [Middleware](https://atlasphp.org/features/middleware/) — Extend with four middleware layers
- [Similarity Search](https://atlasphp.org/features/similarity-search/) — Semantic search over whole-record or chunked embeddings
- [Modalities](https://atlasphp.org/modalities/text/) — Text, images, audio, video, voice, embeddings, and more
- [Conversations](https://atlasphp.org/guides/conversations/) — Multi-turn chat with persistence
- [Voice](https://atlasphp.org/guides/voice-integration/) — Real-time voice conversations
- [Streaming](https://atlasphp.org/guides/streaming/) — SSE and broadcasting
- [Queue](https://atlasphp.org/guides/queue/) — Background execution
- [Testing](https://atlasphp.org/advanced/testing/) — Fakes and assertions

## 🧪 Sandbox

A fully functional chat interface demonstrating Atlas agents in action — multi-agent chat, tool calling, conversation memory, and live image/video generation. Built with Vue 3, Tailwind CSS, and a Laravel JSON API.

<table>
  <tr>
    <td width="50%" align="center">
      <img src="./images/sandbox-agents.png" alt="Switch between multiple agents" width="100%"><br>
      <sub><b>Pick from multiple agents</b> — each with its own model and tools</sub>
    </td>
    <td width="50%" align="center">
      <img src="./images/sandbox-multi-image.png" alt="Multi-image input" width="100%"><br>
      <sub><b>Multi-modal input</b> — attach multiple images in one message</sub>
    </td>
  </tr>
  <tr>
    <td width="50%" align="center">
      <img src="./images/sandbox-thinking.png" alt="Extended thinking in the execution trace" width="100%"><br>
      <sub><b>Extended thinking</b> — stream the model's reasoning and inspect it per step</sub>
    </td>
    <td width="50%" align="center">
      <img src="./images/sandbox-tool-calls.png" alt="Tool call execution trace" width="100%"><br>
      <sub><b>Tool calling</b> — full execution trace with arguments and results</sub>
    </td>
  </tr>
  <tr>
    <td width="50%" align="center">
      <img src="./images/sandbox-text-chat.png" alt="Rich Markdown chat" width="100%"><br>
      <sub><b>Streaming Markdown chat</b> with full execution traces</sub>
    </td>
    <td width="50%" align="center">
      <img src="./images/sandbox-image-generation.png" alt="Generate images" width="100%"><br>
      <sub><b>Image generation</b> rendered inline from a tool call</sub>
    </td>
  </tr>
  <tr>
    <td width="50%" align="center">
      <img src="./images/sandbox-video-generation.png" alt="Generate video" width="100%"><br>
      <sub><b>Video generation</b> that plays right in the thread</sub>
    </td>
    <td width="50%" align="center">
      <img src="./images/sandbox-image-editing.png" alt="Edit an uploaded image" width="100%"><br>
      <sub><b>Image editing</b> — upload a photo and have an agent restyle it</sub>
    </td>
  </tr>
  <tr>
    <td width="50%" align="center">
      <img src="./images/sandbox-retry.png" alt="Retry and branch responses" width="100%"><br>
      <sub><b>Retry &amp; branch</b> — regenerate any reply and step through versions</sub>
    </td>
    <td width="50%" align="center">
      <img src="./images/sandbox-voice-listening.png" alt="Realtime voice — listening" width="100%"><br>
      <img src="./images/sandbox-voice-speaking.png" alt="Realtime voice — AI speaking" width="100%"><br>
      <sub><b>Realtime voice</b> — live waveform as you speak and as the AI replies</sub>
    </td>
  </tr>
</table>

See the [Sandbox README](./sandbox/README.md) for setup instructions and details.

## 🧹 Testing and Code Quality

Atlas uses several tools to maintain high code quality:

```bash
composer check
```

| Tool                                             | Purpose                                                                                                                |
|--------------------------------------------------|------------------------------------------------------------------------------------------------------------------------|
| [Pest](https://pestphp.com)                      | Testing framework                                                                                                      |
| [Larastan](https://github.com/larastan/larastan) | Static analysis                                                                                                        |
| [Laravel Pint](https://laravel.com/docs/pint)    | Code style                                                                                                             |
| [API Audit](./AUDIT.md)                     | Every provider modality verified against **real provider APIs** |
| [Codecov](https://codecov.io/gh/atlas-php/atlas/branch/3.x) | [![codecov](https://codecov.io/gh/atlas-php/atlas/branch/3.x/graph/badge.svg)](https://codecov.io/gh/atlas-php/atlas/branch/3.x) |

## 🤝 Contributing

We welcome contributions!

Support the community by giving a GitHub star. Thank you!

Please see our [Contributing Guide](.github/CONTRIBUTING.md) for details.

## 📄 License

Atlas is open-sourced software licensed under the [MIT license](LICENSE).