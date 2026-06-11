<p align="center">
  <a href="https://atlasphp.org">
    <img src="./images/atlas-logo-3.png" alt="Atlas logo" height="140">
  </a>
</p>

# How Atlas Compares

Atlas is the most complete AI toolkit for Laravel — a full **agent framework**, not just a provider wrapper. It owns its provider layer, runs the tool-call loop, streams and persists everything, and reaches every modality from text to realtime voice.

This page compares Atlas to the two other leading PHP AI libraries: **[Prism](https://github.com/prism-php/prism)** and **[Laravel AI](https://github.com/laravel/ai)** (the official first-party SDK). Atlas plays well with the Laravel ecosystem — for example, MCP works through the excellent [Laravel MCP](https://github.com/laravel/mcp) package — so this isn't about competing with Laravel itself. It's about showing that, feature-for-feature, Atlas is the most refined and complete option.

**Legend:** ✅ built-in · ⚠️ partial or manual wiring · ❌ not available

| Feature | **Atlas** | Prism | Laravel AI |
|---|:---:|:---:|:---:|
| **— Core generation —** | | | |
| Unified text generation | ✅ | ✅ | ✅ |
| Structured output (schema-validated) | ✅ | ✅ | ✅ |
| Streaming (SSE + Laravel Broadcasting) | ✅ | ✅ | ✅ |
| Tool calling | ✅ | ✅ | ✅ |
| Multi-step tool loop | ✅ | ✅ | ✅ |
| Concurrent tool execution | ✅ | ✅ | ⚠️ |
| Multimodal tool results (tools return images/files the model sees) | ⚠️ | ✅ | ⚠️ |
| Reasoning / thinking — configure, stream & persist | ✅ | ✅ | ⚠️ |
| Prompt caching | ✅ | ✅ | ✅ |
| Pre-flight token counting (count input — incl. tools & images — before sending) | ✅ | ❌ | ❌ |
| **— Agent framework —** | | | |
| Agents as first-class classes | ✅ | ❌ | ✅ |
| Sub-agents (delegation, depth & cycle guards, lineage) | ✅ | ❌ | ✅ |
| Concurrent sub-agents (true parallel via forking) | ✅ | ❌ | ⚠️ |
| Conversation persistence & memory | ✅ | ❌ | ✅ |
| Retry & branch responses (regenerate + step through sibling versions) | ✅ | ❌ | ❌ |
| Execution tracking (steps, tools, usage, assets) | ✅ | ❌ | ⚠️ |
| Dedicated media-asset model — auto-stored to disk (S3/local), linked to messages | ✅ | ❌ | ❌ |
| Layered middleware (agent · step · tool · provider) | ✅ | ❌ | ⚠️ |
| Variable interpolation in instructions (`{var}` runtime injection) | ✅ | ❌ | ❌ |
| Queue / async with broadcasting & callbacks | ✅ | ⚠️ | ✅ |
| **— Modalities —** | | | |
| Images | ✅ | ✅ | ✅ |
| Audio — text-to-speech & transcription | ✅ | ✅ | ✅ |
| Music & sound effects | ✅ | ❌ | ❌ |
| Video generation | ✅ | ❌ | ❌ |
| Realtime voice (bidirectional, with tools) | ✅ | ❌ | ❌ |
| Embeddings | ✅ | ✅ | ✅ |
| Multimodal / image embeddings | ❌ | ✅ | ✅ |
| Chunked embeddings + similarity search | ✅ | ❌ | ⚠️ |
| Reranking | ✅ | ❌ | ✅ |
| Moderation | ✅ | ✅ | ⚠️ |
| **— Ecosystem & tooling —** | | | |
| Provider-native tools (web search, code interpreter, file search) | ✅ | ✅ | ✅ |
| MCP tools | ✅ <sup>†</sup> | ✅ | ✅ |
| Provider discovery — list models & voices at runtime | ✅ | ❌ | ❌ |
| Validate API keys / inspect provider capabilities | ✅ | ❌ | ❌ |
| Provider-call observability events (trace every HTTP request, correlation IDs across retries) | ✅ | ❌ | ❌ |
| Automatic provider failover (ordered fallback chain) | ⚠️ | ❌ | ✅ |
| Testing fakes (per modality) | ✅ | ✅ | ✅ |
| Custom / OpenAI-compatible providers | ✅ | ✅ | ✅ |
| First-class providers | 7 <sup>‡</sup> | 13 | 11 |

<sup>†</sup> Atlas composes with the [Laravel MCP](https://github.com/laravel/mcp) package — use your app's MCP tools alongside Atlas; Atlas doesn't reinvent MCP.
<sup>‡</sup> 7 first-class providers (OpenAI, Anthropic, Google, xAI, ElevenLabs, Cohere, Jina) **plus any OpenAI-compatible endpoint** — Ollama, OpenRouter, Together, DeepSeek, Groq, LM Studio, Mistral, Perplexity, and more.

## Why Atlas

Both Prism and Laravel AI are excellent at what they do. Where Atlas pulls ahead is **completeness as an agent framework**:

- **Every modality.** Text, structured output, images, audio, music, sound effects, video, embeddings, reranking, moderation — and **realtime voice**, which neither alternative offers.
- **Real agents.** First-class agent classes, sub-agents with delegation guards, true-parallel sub-agent fan-out, and an auditable parent → child execution tree.
- **Production memory.** Built-in conversation persistence, execution tracking, and asset storage — not something you bolt on.
- **Control at every layer.** Middleware across agent, step, tool, and provider boundaries for logging, auth, and metrics.
- **One consistent surface.** The same fluent API and testing fakes across all of it, with no external AI dependency.

Atlas ships fast — see the [CHANGELOG](./CHANGELOG.md) for what's new, and [atlasphp.org](https://atlasphp.org) for the full docs.
