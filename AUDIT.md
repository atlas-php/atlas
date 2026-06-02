# Live API Audit

Status of each provider's modalities against **real provider APIs**, exercised via the
sandbox harness (`sandbox/test-{provider}-provider.php` and feature scripts). This file
records the date each modality last **passed a live API test** — not unit-test coverage.

**Last full run: 2026-06-02**

Reproduce: `cd sandbox && php test-{provider}-provider.php` (requires the provider's API key in `sandbox/.env`).

### Legend

- ✅ — passed live on the date below
- — — not part of this provider's offering / not in its live suite
- ⚠️ — supported but **not** exercised live (see notes)
- ❌ — could not verify live (blocked — see notes)

## Modality matrix

| Provider    | Text | Stream | Structured | Tools | Vision | Image | TTS | STT | Embeddings | Moderation | Rerank | Video | Voice | Last live pass |
|-------------|:----:|:------:|:----------:|:-----:|:------:|:-----:|:---:|:---:|:----------:|:----------:|:------:|:-----:|:-----:|:--------------:|
| OpenAI      | ✅   | ✅     | ✅         | ✅    | ✅     | ✅    | ✅  | ✅  | ✅         | ✅         | —      | ✅    | ⚠️    | 2026-06-02     |
| Anthropic   | ✅   | ✅     | ✅         | ✅    | ✅     | —     | —   | —   | —          | —          | —      | —     | —     | 2026-06-02     |
| Google      | ✅   | ✅     | ✅         | ✅    | ✅     | ✅    | —   | —   | ✅         | —          | —      | —     | —     | 2026-06-02     |
| xAI         | ✅   | ✅     | ✅         | ✅    | —      | ✅    | ✅  | —   | —          | —          | —      | ✅    | ⚠️    | 2026-06-02     |
| ElevenLabs  | —    | —      | —          | —     | —      | —     | ❌  | ❌  | —          | —          | —      | —     | ⚠️    | —              |
| Cohere      | —    | —      | —          | —     | —      | —     | —   | —   | —          | —          | ❌     | —     | —     | —              |
| Jina        | —    | —      | —          | —     | —      | —     | —   | —   | —          | —          | ❌     | —     | —     | —              |
| Ollama¹     | ⚠️   | ⚠️     | ⚠️         | ⚠️    | —      | —     | —   | —   | —          | —          | —      | —     | —     | —              |
| LM Studio¹  | ⚠️   | ⚠️     | ⚠️         | ⚠️    | —      | —     | —   | —   | —          | —          | —      | —     | —     | —              |

¹ OpenAI-compatible endpoints via the shared `chat_completions` driver.

## Per-provider results (2026-06-02)

| Provider   | Live suite        | Notes |
|------------|-------------------|-------|
| OpenAI     | **35/35 passed**  | Vision verified with a live image-input call (`gpt-4o-mini`). Image generation uses `gpt-image-1` (base64/`b64_json`); generation, store/storeAs, `contents()`, and auto-persist all verified. Also passed: provider tools (web search), media storage. Realtime **Voice** not exercised live. |
| Anthropic  | **17/17 passed**  | Text, streaming, structured, tools, vision (image-from-base64). |
| Google     | **22/22 passed**  | Vision verified with a live image-input call (`gemini-2.5-flash`). Includes Google Search grounding (provider tool), embeddings, image generation. |
| xAI        | **20/20 passed**  | Includes web search + X search (provider tools), image, TTS, video. Realtime Voice not exercised live. |
| ElevenLabs | **not verified**  | Blocked: sandbox `config/atlas.php` has no `elevenlabs` provider block, so the base URL is empty. Package URL resolution is correct — purely a sandbox config gap. TTS / STT / SFX / Music unverified live. |
| Cohere     | **not verified**  | No `COHERE_API_KEY` in `sandbox/.env`. Rerank handler covered by unit tests only. |
| Jina       | **not verified**  | No `JINA_API_KEY` in `sandbox/.env`. Rerank handler covered by unit tests only. |
| Ollama     | **not run**       | Points at a LAN host (`OLLAMA_URL`); not exercised in this run. |
| LM Studio  | **not run**       | Requires a local LM Studio instance; not exercised in this run. |

## Package checks (2026-06-02)

`composer check` — **Pint ✓ · PHPStan 0 errors ✓ · 2768 Pest tests ✓**.
