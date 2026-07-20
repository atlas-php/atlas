# Brief — atlas

*The always-loaded briefing every agent reads first: the story of what we're building and why. One
screen, stable, PII-free.*

## Story

Atlas is a unified AI SDK for Laravel — one fluent, provider-agnostic API for text, images, audio, music, sound effects, video, realtime voice, embeddings, reranking, and moderation, plus a real agent framework on top. It exists so PHP developers build AI-enabled products against a single consistent surface instead of stitching together per-vendor SDKs and payload formats. In its v3 line Atlas owns its entire provider layer, talking directly to vendor HTTP APIs, so the result is a self-contained package that swaps providers, models, and modalities by changing a string — no application code changes.

## Why it exists

Most AI libraries return a response and stop there. Real applications hit everything after the happy path — retries, typed errors, streaming that survives interruption, tool-call loops, cost tracking, and audit trails — and each vendor exposes a different SDK and payload shape. Laravel developers who want breadth (many providers, many modalities) and depth (agents, persistence, observability) without vendor lock-in are left gluing SDKs together. Atlas absorbs that work behind one framework-aware, testable interface. When a decision is unclear, the guiding principles are: provider-agnostic and swappable, self-contained with no hard app dependency, and deterministic under test.

## Users / ICP

- PHP and Laravel developers building AI features who want one API across many providers and modalities.
- They accomplish generation, embeddings and similarity search, tool-using agents, realtime voice, and cost-saving batch jobs without vendor lock-in.
- What matters most to them: provider-agnostic and swappable, self-contained (no hard app dependency), framework-aware, deterministic and testable, with optional persistence and audit trails when needed.

## Scope

- **Active areas:** the `atlas` package — the provider layer (OpenAI, Anthropic, Google Gemini, xAI, ElevenLabs, Cohere, Jina, and any OpenAI-compatible endpoint), all generation modalities, the agent framework (executor, tool loop, sub-agents, middleware), embeddings and chunking, persistence, realtime voice, batch, and the VitePress docs site.
- **Out of scope:** any other Atlas repo (reference only, unless a task explicitly names it); the upstream Prism repo, kept only as a temporary working copy for upstream PRs and never a dependency of Atlas.

## External Systems

- `OpenAI`, `Anthropic`, `Google Gemini`, `xAI` — text, image, audio, video, embedding, moderation, and batch generation.
- `ElevenLabs` — realtime voice, music, and sound-effect audio generation.
- `Cohere`, `Jina` — document reranking.
- `OpenAI-compatible endpoints (Ollama, Groq, DeepSeek, LM Studio, and similar)` — self-hosted and third-party model access through the shared ChatCompletions and Responses drivers.
- `atlasphp.org` — the public documentation site built from `docs/`.

---
*Editing this file? Follow the standard first: [`guides/docs-brief.md`](./guides/docs-brief.md).*
