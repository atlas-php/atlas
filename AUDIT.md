# Live API Audit

Status of each provider's modalities against **real provider APIs**, exercised via the
sandbox harness (`sandbox/test-{provider}-provider.php` and feature scripts). This file
records the date each modality last **passed a live API test** — not unit-test coverage.

**Last full run: 2026-06-02** · **Provider-tools run: 2026-06-06**

Reproduce:
- Per-provider suites: `cd sandbox && php test-{provider}-provider.php`
- Provider-native tools (web search / fetch / file search / code / domain scoping):
  `cd sandbox && php test-provider-tools-live.php`

Both require the provider's API key in `sandbox/.env`.

### Legend

- ✅ — passed live on the date below
- — — not part of this provider's offering / not in its live suite
- ⚠️ — supported but **not** fully exercised live, or works but with a gap (see notes)
- ❌ — could not verify live (blocked — see notes)

## Modality matrix

| Provider    | Text | Stream | Structured | Tools | Prov.Tools | Vision | Image | TTS | STT | Embeddings | Moderation | Rerank | Video | Voice | Last live pass |
|-------------|:----:|:------:|:----------:|:-----:|:----------:|:------:|:-----:|:---:|:---:|:----------:|:----------:|:------:|:-----:|:-----:|:--------------:|
| OpenAI      | ✅   | ✅     | ✅         | ✅    | ✅         | ✅     | ✅    | ✅  | ✅  | ✅         | ✅         | —      | ✅    | ⚠️    | 2026-06-06     |
| Anthropic   | ✅   | ✅     | ✅         | ✅    | ✅         | ✅     | —     | —   | —   | —          | —          | —      | —     | —     | 2026-06-06     |
| Google      | ✅   | ✅     | ✅         | ✅    | ⚠️         | ✅     | ✅    | —   | —   | ✅         | —          | —      | —     | —     | 2026-06-06     |
| xAI         | ✅   | ✅     | ✅         | ✅    | ✅         | —      | ✅    | ✅  | —   | —          | —          | —      | ✅    | ⚠️    | 2026-06-06     |
| ElevenLabs  | —    | —      | —          | —     | —          | —      | —     | ❌  | ❌  | —          | —          | —      | —     | ⚠️    | —              |
| Cohere      | —    | —      | —          | —     | —          | —      | —     | —   | —   | —          | —          | ❌     | —     | —     | —              |
| Jina        | —    | —      | —          | —     | —          | —      | —     | —   | —   | —          | —          | ❌     | —     | —     | —              |
| Ollama¹     | ⚠️   | ⚠️     | ⚠️         | ⚠️    | —          | —      | —     | —   | —   | —          | —          | —      | —     | —     | —              |
| LM Studio¹  | ⚠️   | ⚠️     | ⚠️         | ⚠️    | —          | —      | —     | —   | —   | —          | —          | —      | —     | —     | —              |

¹ OpenAI-compatible endpoints via the shared `chat_completions` driver.

## Provider tools (live, 2026-06-06)

Provider-native tools each provider executes server-side. Verified end-to-end **through Atlas**
(`Atlas::text(...)->withProviderTools([...])`) via `sandbox/test-provider-tools-live.php`, plus
raw-API shape probes for tools that need external resources (a vector store, a container).

- ✅ — full end-to-end pass: the model **used the tool and based its answer on the live results**, and Atlas captured the calls/citations (`providerToolCalls` / `annotations`).
- ◐ — native request shape verified live (HTTP 200), not run end-to-end (needs an external resource like a vector store or container).
- ⚠️ — the model uses the tool and the answer is correctly grounded, but Atlas does not yet surface it as `providerToolCalls`/`annotations`.

| Provider  | web_search | web_fetch | file_search | code_interpreter | google_search | code_execution | x_search |
|-----------|:----------:|:---------:|:-----------:|:----------------:|:-------------:|:--------------:|:--------:|
| OpenAI    | ✅          | —         | ◐           | ◐                | —             | —              | —        |
| Anthropic | ✅          | ✅         | —           | —                | —             | —              | —        |
| Google    | —          | —         | —           | —                | ⚠️            | ⚠️             | —        |
| xAI       | ✅          | —         | —           | —                | —             | —              | ◐        |

Notes:
- **Domain scoping** (`allowedDomains` / `blockedDomains`) verified live on OpenAI/xAI (`filters`)
  and Anthropic (top-level). Anthropic returns citations as `annotations`.
- **OpenAI** `file_search` requires `vector_store_ids` and `code_interpreter` requires a
  `container` — both confirmed via live 400/200 shape probes; not run end-to-end (no live
  vector store / sandboxed container in the harness).
- **Google** `google_search` (grounding) and `code_execution` fire and ground answers correctly
  live (returned current, beyond-cutoff data), but Atlas's Google `ResponseParser` does not yet
  map `groundingMetadata` / `codeExecutionResult` into `providerToolCalls` / `annotations` —
  observability gap, functional path works. **Follow-up:** wire Google grounding observability.

## Per-provider results

| Provider   | Live suite (2026-06-06)    | Notes |
|------------|----------------------------|-------|
| OpenAI     | **33/35 passed**           | 2 unrelated failures this run: Sora-2 video blocked by content moderation, and a TTS `audio/speech` cURL timeout — both external/transient, not code. Provider tools: `web_search` (+ domain filters) verified end-to-end. |
| Anthropic  | **17/17 passed**           | Text, streaming, structured, tools, vision. Provider tools: `web_search` + `web_fetch` verified end-to-end with citations (newly supported in v3.3.0). |
| Google     | **provider tools checked** | Full modality suite last run 2026-06-02 (22/22). Provider tools `google_search` + `code_execution` exercised live 2026-06-06 — functional, observability gap (above). |
| xAI        | **20/20 passed**           | Provider tools: `web_search` (+ domain filters) end-to-end; `x_search` shape-verified. |
| ElevenLabs | **not verified**           | Blocked: sandbox `config/atlas.php` has no `elevenlabs` provider block, so the base URL is empty. Package URL resolution is correct — purely a sandbox config gap. |
| Cohere     | **not verified**           | No `COHERE_API_KEY` in `sandbox/.env`. Rerank handler covered by unit tests only. |
| Jina       | **not verified**           | No `JINA_API_KEY` in `sandbox/.env`. Rerank handler covered by unit tests only. |
| Ollama     | **not run**                | Points at a LAN host (`OLLAMA_URL`); not exercised in this run. |
| LM Studio  | **not run**                | Requires a local LM Studio instance; not exercised in this run. |

## Package checks (2026-06-06)

`composer check` — **Pint ✓ · PHPStan 0 errors ✓ · 2828 Pest tests ✓**.
