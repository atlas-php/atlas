# Brief - atlas

## Story

Atlas is a unified AI SDK for Laravel — one fluent, provider-agnostic API for text, image, audio, video, music, speech, embeddings, moderation, rerank, agents, voice, and batch generation. It exists so PHP developers can build AI-enabled products against a single consistent surface instead of stitching together per-vendor SDKs and payload formats. In its v3 line it owns its entire provider layer, talking directly to vendor HTTP APIs, so the outcome is a self-contained package that swaps providers, models, and modalities without touching application code.

## Users / ICP

- PHP and Laravel developers building AI features who want one API across many providers and modalities.
- They accomplish generation, embeddings/similarity search, tool-using agents, real-time voice, and cost-saving batch jobs without vendor lock-in.
- Qualities that matter: provider-agnostic and swappable, self-contained (no hard app dependency), framework-aware, deterministic and testable, with persistence/audit trails when needed.

## Scope

- **Active areas:** the `atlas` package — provider layer (OpenAI, Anthropic, Google, xAI, ElevenLabs, Cohere, Jina, OpenAI-compatible), all generation modalities, agents/executor/tool loop, embeddings and chunking, persistence, voice, batch, and the VitePress docs site.
- **Out of scope:** any other Atlas repo (reference only, unless a task explicitly names it); the upstream Prism repo, kept only as a temporary working copy for upstream PRs and never Atlas's dependency.

## External Systems

- `OpenAI`, `Anthropic`, `Google Gemini`, `xAI` — text, image, audio, video, embedding, moderation, and batch generation.
- `ElevenLabs` — voice, music, and sound-effect audio generation.
- `Cohere`, `Jina` — document reranking.
- `OpenAI-compatible endpoints (Ollama, LM Studio)` — self-hosted / local model access via the shared ChatCompletions and Responses drivers.
- `atlasphp.org` — the public documentation site built from `docs/`.
