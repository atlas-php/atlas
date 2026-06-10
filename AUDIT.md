# Live API Audit

Status of each provider's modalities against **real provider APIs**, exercised via the
sandbox harness (`sandbox/test-{provider}-provider.php` and feature scripts). This file
records the date each modality last **passed a live API test** — not unit-test coverage.

**Last full run: 2026-06-10** (all 4 providers — **97/97 green**; the two 2026-06-09 transient failures (OpenAI TTS, xAI voices) now pass) · **Structured output strict-mode (builder, live OpenAI/Google/Anthropic): 2026-06-10** · **Error handling & resilience (all 4 providers): 2026-06-10** · **Error & request context tracing (all 4 providers, 56/56): 2026-06-10** · **Forced tool choice (all 4 providers): 2026-06-09** · **Image-to-image (reference media): 2026-06-08** · **Prompt caching + media replay: 2026-06-07** · **Provider-tools run: 2026-06-06**

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
| OpenAI      | ✅   | ✅     | ✅         | ✅    | ✅         | ✅     | ✅    | ✅  | ✅  | ✅         | ✅         | —      | ✅    | ⚠️    | 2026-06-10     |
| Anthropic   | ✅   | ✅     | ✅         | ✅    | ✅         | ✅     | —     | —   | —   | —          | —          | —      | —     | —     | 2026-06-10     |
| Google      | ✅   | ✅     | ✅         | ✅    | ⚠️         | ✅     | ✅    | —   | —   | ✅         | —          | —      | —     | —     | 2026-06-10     |
| xAI         | ✅   | ✅     | ✅         | ✅    | ✅         | ✅     | ✅    | ✅  | —   | —          | —          | —      | ✅    | ⚠️    | 2026-06-10     |
| ElevenLabs  | —    | —      | —          | —     | —          | —      | —     | ❌  | ❌  | —          | —          | —      | —     | ⚠️    | —              |
| Cohere      | —    | —      | —          | —     | —          | —      | —     | —   | —   | —          | —          | ❌     | —     | —     | —              |
| Jina        | —    | —      | —          | —     | —          | —      | —     | —   | —   | —          | —          | ❌     | —     | —     | —              |
| Ollama¹     | ⚠️   | ⚠️     | ⚠️         | ⚠️    | —          | —      | —     | —   | —   | —          | —          | —      | —     | —     | —              |
| LM Studio¹  | ⚠️   | ⚠️     | ⚠️         | ⚠️    | —          | —      | —     | —   | —   | —          | —          | —      | —     | —     | —              |

¹ OpenAI-compatible endpoints via the shared `chat_completions` driver.

## Provider tools (live, 2026-06-06; full coverage re-run 2026-06-10)

Provider-native tools each provider executes server-side. Verified end-to-end **through Atlas**
(`Atlas::text(...)->withProviderTools([...])`). Every registry entry except `file_search` is
covered by `sandbox/test-provider-tools-coverage-live.php` (9/9 passed 2026-06-10);
`file_search` uses a raw-API shape probe (needs an external vector store).

- ✅ — full end-to-end pass: the model **used the tool and based its answer on the live results**, and Atlas captured the calls/citations (`providerToolCalls` / `annotations`).
- ◐ — native request shape verified live (HTTP 200), not run end-to-end (needs an external resource like a vector store or container).
- ⚠️ — the model uses the tool and the answer is correctly grounded, but Atlas does not yet surface it as `providerToolCalls`/`annotations`.

| Provider  | web_search | web_fetch | file_search | code_interpreter | google_search | code_execution | x_search |
|-----------|:----------:|:---------:|:-----------:|:----------------:|:-------------:|:--------------:|:--------:|
| OpenAI    | ✅          | —         | ◐           | ✅                | —             | —              | —        |
| Anthropic | ✅          | ✅         | —           | —                | —             | —              | —        |
| Google    | —          | —         | —           | —                | ⚠️            | ⚠️             | —        |
| xAI       | ✅          | —         | —           | ✅                | —             | —              | ✅        |

(`—` = provider has no such tool / not in the registry, not a failure.)

Notes:
- **Registry is the enforced support matrix.** `ProviderToolRegistry::assertSupported()` rejects an
  unsupported tool for a tracked provider before the request is sent, so every ✅/◐ above must stay
  truthful — an entry here is a promise the guard will allow it.
- **xAI `code_interpreter`** verified end-to-end 2026-06-10 (grok-4 + grok-3 ran Python, returned the
  exact result). xAI accepts Atlas's OpenAI-shaped payload (including the `container` field).
- **xAI `file_search`** (collections search) is **not** in the registry — Atlas's `file_search` payload
  was not live-verified against an xAI collection. Add only after an end-to-end pass with a real store.
- **Domain scoping** (`allowedDomains` / `blockedDomains`) verified live on OpenAI/xAI (`filters`)
  and Anthropic (top-level). Anthropic returns citations as `annotations`.
- **OpenAI** `file_search` requires `vector_store_ids` (confirmed via live shape probe; not run
  end-to-end — no live vector store in the harness). `code_interpreter` now runs end-to-end.
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

## Image-to-image / reference media (live, 2026-06-08)

Reference image input to **image generation** — `Atlas::image(...)->withMedia([...])` — conditions the output on the supplied image (identity-preserving edits, style transfer, reference-anchored generation). Verified live in each provider's image suite: a solid red-square reference + the prompt "keep this red square, put it on a blue background" returns a red square on blue — proof the reference reached the model, not just the prompt.

| Provider  | Model                      | Endpoint / mechanism                              | Result (live)                      |
|-----------|----------------------------|---------------------------------------------------|------------------------------------|
| Google    | gemini-2.5-flash-image     | `generateContent` + `inline_data` part            | ✅ red square preserved on blue     |
| OpenAI    | gpt-image-1                | `/images/edits` (multipart, `image[]`)            | ✅ red square preserved on blue     |
| xAI       | grok-imagine-image-quality | `/images/edits` (JSON, `image_url` part, ≤3 refs) | ✅ red square preserved on blue     |
| Anthropic | —                          | no image generation                               | — not part of its offering         |

Each provider's image handler owns its own request shape — xAI does **not** accept OpenAI's multipart edits (it returns HTTP 415; it requires JSON), so it has a dedicated handler. Saved outputs: `sandbox/storage/providers/{google,openai,xai}/image-*-edit.png`. **Future image audits must re-run the reference-media test in each provider suite.**

## Forced tool choice (live, 2026-06-09)

Provider-normalized `tool_choice` verified end-to-end through Atlas (`sandbox/test-force-tools-live.php`). `->forceTools()` (tool_choice = required) is sent to each provider in its own shape (OpenAI/xAI string `required`, Anthropic `{type:any}`, Google `tool_config.mode=ANY`); the executor then relaxes the choice to `auto` after the opening step so the model still produces a final reply. Each test forces the model to call a probe tool on a trivial "just say hello" prompt it would otherwise answer in plain text.

| Provider  | Model              | Forced tool called | Final reply after relaxation |
|-----------|--------------------|:------------------:|:----------------------------:|
| OpenAI    | gpt-4o-mini        | ✅                  | ✅ "Hello! How are you today?" |
| Anthropic | claude-sonnet-4-5  | ✅                  | ✅ "Hello! 👋"                 |
| Google    | gemini-2.5-flash   | ✅                  | ✅ "Hello back to you!"        |
| xAI       | grok-4.3           | ✅                  | ✅ "Hello!"                    |

**Specific named tool** (`->toolChoice(ToolChoice::tool('log_mood'))`) verified live on **all four providers**: with two tools available (`get_time` + `log_mood`), the forced tool is the one that opens the turn (not the other) — proving the choice targets the named tool, not just "some tool". Result: **16/16 live checks passed** (4 forceTools + 4 specific-tool, ×2 assertions). (Note: forcing a tool on OpenAI requires a valid strict-mode function schema — Atlas tools with `parameters()` already emit `additionalProperties:false`.)

## Structured output — strict-mode schema (live, 2026-06-10)

Audited `Atlas::...->withSchema($schema)->asStructured()` across every structured-capable
provider using the **fluent `Schema` builder** (the documented public API), not hand-written
arrays. Verified end-to-end through Atlas with builder schemas covering flat fields, arrays,
nested objects, and an `->optional()` field.

**Bug found & fixed (this run):** builder schemas **400'd on OpenAI / xAI / OpenAI-compatible
providers**. Those use OpenAI's strict `json_schema` (`strict: true`), which requires
`additionalProperties: false` on every object and **all** keys in `required` — the builder
emits neither, and `->optional()` produced keys absent from `required`. The prior sandbox
tests passed only because they hand-wrote raw arrays with `additionalProperties:false` baked
in, never exercising the builder. Fix: `Schema\StrictSchema::normalize()` applied in the
OpenAI + ChatCompletions structured handlers (xAI inherits OpenAI) — recursively adds
`additionalProperties:false`, lists all keys in `required`, and expresses optionals as a
nullable type union. Google/Anthropic are intentionally **not** normalized (different
mechanisms; Gemini's `responseSchema` 400s on `additionalProperties`).

| Provider  | Model                     | Mechanism                         | Normalizer | Builder result (live)                              |
|-----------|---------------------------|-----------------------------------|:----------:|----------------------------------------------------|
| OpenAI    | gpt-4o-mini               | strict `json_schema`              | ✅ applied | ✅ flat, nested, optional (`phone: null`)           |
| xAI       | (inherits OpenAI handler) | strict `json_schema`              | ✅ inherited | ⚠️ not re-run live this session; same code path + unit |
| ChatCompletions (Ollama/LM Studio) | — | strict `json_schema`        | ✅ applied | ⚠️ unit only (no local server in run)              |
| Google    | gemini-2.5-flash          | `responseSchema` (OpenAPI subset) | ❌ must not | ✅ flat, optional (key omitted: `{"name":"Dana"}`)  |
| Anthropic | claude-sonnet-4-5         | forced tool `input_schema`        | ❌ not needed | ✅ flat, optional (key omitted: `{"name":"Dana"}`)  |

Before the fix (live, same builder schema): `OpenAI 400 — Invalid schema for response_format
'…': 'additionalProperties' is required to be supplied and to be false.` After: parsed object
returned.

**Provider semantics note (not a bug):** under OpenAI strict mode an optional field returns as
explicit `null`; Google/Anthropic **omit** the key. Consumers should read optionals as
`$data['key'] ?? null` regardless of provider.

Unit coverage: `tests/Unit/Schema/StrictSchemaTest.php` (16 cases — happy + every negative
branch) plus builder-based assertions in the OpenAI and ChatCompletions handler tests.
Regression guard: sandbox structured cases (`test-openai-provider.php`,
`test-xai-provider.php`, `test-lmstudio-provider.php`) now drive the builder incl.
`->optional()`, so the next live sweep exercises the real path.

## Error handling & resilience (live, 2026-06-10)

End-to-end verification of the error-handling/HTTP/retry/queue work against **real provider APIs**. Classification is driven purely by HTTP status code + exception type + structural JSON shape — never by parsing message words. **19/19 live checks passed** across OpenAI / Anthropic / Google / xAI.

**Error classification (bad model id) — real provider message + typed exception by status:**

| Provider  | Model | HTTP | Atlas exception | Provider message (live) |
|-----------|-------|:----:|-----------------|-------------------------|
| OpenAI    | bad   | 400  | `InvalidRequestException` | "The requested model '…' does not exist." |
| Anthropic | bad   | 404  | `ModelNotFoundException`  | "model: …" |
| Google    | bad   | 404  | `ModelNotFoundException`  | "models/… is not found …" |
| xAI       | bad   | 400  | `InvalidRequestException` | "Model not found: …" |

**Other live checks (all ✅):**

| Area | Check | Result |
|------|-------|--------|
| Happy path | `asText` (non-streaming) | ✅ all 4 providers |
| Happy path | `asStream` (streaming) | ✅ all 4 providers (no regression from parser error-detection) |
| Auth | bad key → caught via `catch (ProviderException)` | ✅ `AuthenticationException` (401) |
| Auth | `Atlas::provider(...)->models()` with bad key | ✅ `AuthenticationException` (interrogation no longer leaks raw) |
| Connection | unroutable host → typed error | ✅ `ConnectionException` (catchable as `AtlasException`/`ProviderException`) |
| Retry | `->withRetry(errors: 2)` on a connection failure | ✅ retried exactly 2× then threw |
| Timeout | `->withTimeout(1)` overrides the provider timeout | ✅ failed in ~1.0s |
| Timeout | `ATLAS_TIMEOUT` global default applies to a provider with no own timeout | ✅ failed in ~1.0s |

Reproduce: `cd sandbox` and run an inline script booting `bootstrap.php` (see the Phase audit scripts). Requires OpenAI/Anthropic/Google/xAI keys in `sandbox/.env`.

**Not live-verified (no API key in sandbox):** ElevenLabs, Cohere, Jina, Ollama. Their error-message extraction (`detail` / top-level `message` / string `error`) and the mid-stream error detection are covered by unit tests, not a live call.

**Mid-stream provider errors** (a `data: {"error":…}` / `event: error` arriving on a 200 stream) are detected and thrown as a model-carrying `ProviderException` — verified by unit tests with fixture SSE streams (can't be triggered on demand from a healthy provider).

## Error & request context tracing (live, 2026-06-10)

Validates the observability additions end-to-end against **real provider APIs** via
`sandbox/test-error-context-live.php`. **56/56 live checks passed** across OpenAI / Anthropic / Google / xAI.

Per provider, two phases:

1. **Successful call** — `ProviderRequestStarted` + `ProviderRequestCompleted` fire carrying the **same** `correlationId` and the correct `provider` + `model`.
2. **Bad-key call** — the provider's **real** error message surfaces on `ProviderException::providerMessage`, `responseBody()` returns the decoded error, and `ProviderRequestFailed` carries `provider` + `correlationId`.

| Provider  | Bad-key status | Atlas exception | Provider message (live) |
|-----------|:--------------:|-----------------|-------------------------|
| OpenAI    | 401 | `AuthenticationException` | "Incorrect API key provided: sk-…. You can find your API key at …" |
| Anthropic | 401 | `AuthenticationException` | "invalid x-api-key" |
| Google    | 400 | `InvalidRequestException` | "API key not valid. Please pass a valid API key." |
| xAI       | 400 | `InvalidRequestException` | "Incorrect API key provided: sk***ef. …" |

> Google/xAI classify a bad key as **400** (not 401) — those already carried `providerMessage`; this release specifically restored the **401** auth message for OpenAI/Anthropic, which previously showed a generic "Authentication failed" with an empty `providerMessage`.

## Per-provider results

| Provider   | Live suite                 | Notes |
|------------|----------------------------|-------|
| Anthropic  | **17/17 passed** (06-10)   | Text, streaming, structured, tools, vision — all green. Prompt caching (cache_control) + media-replay vision verified live (above). |
| Google     | **23/23 passed** (06-10)   | Full modality suite green, incl. image-to-image. Provider-tools observability gap unchanged (`google_search`/`code_execution` ground correctly but aren't mapped to `providerToolCalls`). |
| OpenAI     | **36/36 passed** (06-10)   | Text/tools/streaming/structured/vision/image/STT/video all green. TTS (the 06-09 transient cURL-28 timeout) passed this run. |
| xAI        | **21/21 passed** (06-10)   | Full suite green incl. image-to-image. `voices` list (the 06-09 transient HTTP 500) passed this run. Note: `grok-2-vision-1212` retired → use `grok-4.3` for vision. |
| ElevenLabs | **not verified**           | Blocked: sandbox `config/atlas.php` has no `elevenlabs` provider block, so the base URL is empty. Package URL resolution is correct — purely a sandbox config gap. |
| Cohere     | **not verified**           | No `COHERE_API_KEY` in `sandbox/.env`. Rerank handler covered by unit tests only. |
| Jina       | **not verified**           | No `JINA_API_KEY` in `sandbox/.env`. Rerank handler covered by unit tests only. |
| Ollama     | **not run**                | Points at a LAN host (`OLLAMA_URL`); not exercised in this run. |
| LM Studio  | **not run**                | Requires a local LM Studio instance; not exercised in this run. |

## Package checks (2026-06-10)

`composer check` — **Pint ✓ · PHPStan 0 errors ✓ · 3166 Pest tests ✓** (incl. the full error-handling suite, the observability coverage — auth/authz provider messages, `responseBody()`/`rawResponse()`, transport-event correlation id + provider/model, the `ProviderRequestContext` value object — and the structured-output strict-mode normalizer: `StrictSchema` unit coverage plus builder-based handler assertions).
