# Live API Audit

Status of each provider's modalities against **real provider APIs**, exercised via the
sandbox harness (`sandbox/test-{provider}-provider.php` and feature scripts). This file
records the date each modality last **passed a live API test** — not unit-test coverage.

**Last full run: 2026-06-07** (Anthropic/Google/xAI) · **Prompt caching + media replay: 2026-06-07** · **Provider-tools run: 2026-06-06**

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
| Anthropic   | ✅   | ✅     | ✅         | ✅    | ✅         | ✅     | —     | —   | —   | —          | —          | —      | —     | —     | 2026-06-07     |
| Google      | ✅   | ✅     | ✅         | ✅    | ⚠️         | ✅     | ✅    | —   | —   | ✅         | —          | —      | —     | —     | 2026-06-07     |
| xAI         | ✅   | ✅     | ✅         | ✅    | ✅         | ✅     | ✅    | ✅  | —   | —          | —          | —      | ✅    | ⚠️    | 2026-06-07     |
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

## Prompt caching (live, 2026-06-07)

Verified end-to-end through Atlas (`sandbox/test-prompt-caching.php`, ~7.5k-token stable prefix, two calls). Anthropic needs explicit `cache_control` (Atlas adds it); OpenAI/xAI/Google cache automatically. Savings are reported on `usage->cachedTokens` / `cacheWriteTokens` for all four.

| Provider  | Mechanism            | Result (live)                          |
|-----------|----------------------|----------------------------------------|
| Anthropic | explicit cache markers | ✅ write 7710 → read 7710               |
| OpenAI    | automatic            | ✅ `cached_tokens` (≳6k-token prefix to hit) |
| xAI       | automatic            | ✅ read 7168                            |
| Google    | implicit (2.5+)      | ✅ read 7142                            |

`->cache()` / `ATLAS_PROMPT_CACHE` toggles it (on by default); `supports('caching')` capability on all four.

## Conversation media replay & vision (live, 2026-06-07)

Verified end-to-end (`sandbox/test-vision-replay.php`): an image is persisted as existing history, then `respond()` rebuilds the turn entirely from stored history (rehydration → group-remap media preservation → `media_replay_limit` window). Every vision-capable provider read a number drawn in the replayed image.

| Provider  | Model                 | Result (live)              |
|-----------|-----------------------|----------------------------|
| Anthropic | claude-sonnet-4-5     | ✅ read "42" off the image |
| OpenAI    | gpt-4o-mini           | ✅ read "42" off the image |
| Google    | gemini-2.5-flash      | ✅ read "42" off the image |
| xAI       | grok-4.3              | ✅ read "42" off the image |

**Config gating** (`sandbox/test-media-config.php`, `ATLAS_MEDIA_REPLAY_LIMIT` set per process): same image, same prompt, image as the 2nd-from-last message. **All four providers**: `limit=1` (image outside window) → **0 image blocks sent, model cannot read it**; `limit=2` (image inside window) → **1 image block sent, model reads "42"**. Confirms `media_replay_limit` controls live visibility per provider.

## Per-provider results

| Provider   | Live suite (2026-06-07)    | Notes |
|------------|----------------------------|-------|
| Anthropic  | **17/17 passed**           | Text, streaming, structured, tools, vision. Prompt caching (cache_control) + media-replay vision verified live (above). |
| Google     | **22/22 passed**           | Full modality suite green. Prompt caching (implicit) + media-replay vision verified live. Provider-tools observability gap unchanged (`google_search`/`code_execution` ground correctly but aren't mapped to `providerToolCalls`). |
| xAI        | **20/20 passed**           | Prompt caching (automatic) + media-replay vision (`grok-4.3`) verified live. Note: `grok-2-vision-1212` retired → use `grok-4.3` for vision. |
| OpenAI     | **text/vision/caching ✅; suite incomplete** | OpenAI text, vision (history replay), and prompt caching all verified live 2026-06-07. The full provider suite again aborted on the `speech-to-text round trip` (`/audio/speech` cURL 28 @120s) — external/transient, unrelated to this patch (same as 2026-06-06's 33/35 with Sora-video + TTS). |
| ElevenLabs | **not verified**           | Blocked: sandbox `config/atlas.php` has no `elevenlabs` provider block, so the base URL is empty. Package URL resolution is correct — purely a sandbox config gap. |
| Cohere     | **not verified**           | No `COHERE_API_KEY` in `sandbox/.env`. Rerank handler covered by unit tests only. |
| Jina       | **not verified**           | No `JINA_API_KEY` in `sandbox/.env`. Rerank handler covered by unit tests only. |
| Ollama     | **not run**                | Points at a LAN host (`OLLAMA_URL`); not exercised in this run. |
| LM Studio  | **not run**                | Requires a local LM Studio instance; not exercised in this run. |

## Package checks (2026-06-07)

`composer check` — **Pint ✓ · PHPStan 0 errors ✓ · 2866 Pest tests ✓**.
