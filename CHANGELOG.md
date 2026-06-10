# Changelog

All notable changes to Atlas will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com), and this project adheres to [Semantic Versioning](https://semver.org).

[All Releases](https://github.com/atlas-php/atlas/releases)

---

## [Unreleased]

### Added

- New typed exceptions for specific provider failures: `ConnectionException` (network), `ModelNotFoundException` (unknown model), `InvalidRequestException` (bad request), and `ServerException` (provider error).
- xAI now supports the `CodeInterpreter` provider tool.
- `voice.route_middleware` config to protect the voice routes, e.g. `['auth:sanctum', 'throttle:60,1']`.
- `ProviderException::responseBody()` and `rawResponse()` expose the provider's raw error response for debugging, without digging through the previous exception.
- Provider request events (`ProviderRequestStarted` / `Completed` / `Failed` / `Retrying`) now carry a `correlationId` (stable across retries) plus the `provider` and `model`, so you can correlate and attribute every HTTP call to a provider.

### Changed

- A single `catch (ProviderException)` now handles every provider failure. Catch a subclass for specific cases.
- "Overloaded" provider responses are now retried, like rate limits.
- Attaching an unsupported provider-native tool (e.g. xAI's X Search on OpenAI) now throws `UnsupportedFeatureException` up front instead of failing at the provider.

### Fixed

- Structured output via the `Schema` builder now works on OpenAI, xAI, and OpenAI-compatible providers (Ollama, LM Studio) — previously these 400'd because strict JSON-schema requirements (`additionalProperties: false`, all keys required, optionals as nullable) weren't applied. Optional fields now round-trip as nullable.
- Rate limits and transient server errors now retry automatically.
- Per-call and global timeouts are honored, including on queued requests.
- Network failures and mid-stream errors now surface as exceptions instead of returning truncated, successful-looking responses.
- Interrupted streams broadcast the usage and finish reason captured before the break, so cost tracking survives.
- Queued requests fail fast on unrecoverable errors (bad key, bad request, unknown model) instead of burning every retry.
- Per-request middleware (`->withMiddleware()`) now runs on queued image, audio, video, speech, embedding, moderation, rerank, music, and sound-effect requests — previously it was silently dropped on queue (text and agent requests were unaffected).
- Queuing a request with a closure middleware now fails fast with a clear error instead of crashing the worker; use class-based middleware for queued requests.
- Google tool calls missing a function name now degrade gracefully instead of erroring mid-parse.
- Listing models and voices now reports failures like any other call.
- Error messages now carry the provider's real reason across all providers.
- Authentication (401) and authorization (403) failures now include the provider's real error message (e.g. "Incorrect API key provided") instead of a generic message.
- Queued failures cap their broadcast error so it can't exceed the socket limit and drop the event.

### Migration

No breaking changes for most users. Check these only if they apply:

- **You catch specific exceptions** (auth, rate-limit) before `ProviderException` — keep those `catch` blocks first; `ProviderException` now catches them too.
- **You use `reasoning_timeout`** — removed. Set a longer per-call timeout instead.
- **You read streamed responses** — mid-stream errors now throw. Wrap your read loop in try/catch.
- **You attach provider-native tools to providers that don't support them** — now throws `UnsupportedFeatureException` up front.
- **You set voice config in a published config file** — `voice_route_prefix` and `voice_session_ttl` moved to `voice.route_prefix` / `voice.session_ttl`. Old keys still work; migrate when convenient.

---

## [v3.4.0](https://github.com/atlas-php/atlas/releases/tag/v3.4.0) - 2026-06-09

### Added

- Force tool calls with `->forceTools()` or `->toolChoice(...)` — require any tool, a specific tool, `auto`, or `none`. Atlas sends the right format for every provider; in agent loops it forces the first step then relaxes so the model still replies.

### Changed

- Broadcasted tool-call events now cap each argument/result/error to **2048 bytes** by default (configurable via `ATLAS_BROADCAST_MAX_TOOL_PAYLOAD`; set `0` for uncapped), so large tool payloads stay under socket transport limits (Pusher/Reverb) instead of silently dropping the event or closing the connection.

### Migration

No breaking changes. Broadcast tool payloads are capped to 2048 bytes by default — set `ATLAS_BROADCAST_MAX_TOOL_PAYLOAD=0` to broadcast uncapped, or a higher byte value to suit your transport.

---

## [v3.3.2](https://github.com/atlas-php/atlas/releases/tag/v3.3.2) - 2026-06-08

### Fixed

- Image generation now sends `->withMedia()` reference images to the provider, so image-to-image works (identity-preserving edits, style transfer, reference-anchored generation) on Google, OpenAI, and xAI. The reference was previously dropped from the request payload, so every generation ran text-only regardless of any reference supplied.

### Migration

No breaking changes — drop-in upgrade. No consumer action required.

---

## [v3.3.1](https://github.com/atlas-php/atlas/releases/tag/v3.3.1) - 2026-06-07

### Added

- **Prompt caching (on by default)** — repeated tokens cost less. Disable with `ATLAS_PROMPT_CACHE=false` or `->cache(false)`; savings show in `usage->cachedTokens`.
- **`ATLAS_MEDIA_REPLAY_LIMIT`** (default `2`) — how many recent messages resend their images each turn.

### Fixed

- **The AI can now see images shared earlier in a chat** — attachments are replayed from history instead of dropped to text.

### Migration

None — drop-in. Set `ATLAS_PROMPT_CACHE=false` to disable caching.

---

## [v3.3.0](https://github.com/atlas-php/atlas/releases/tag/v3.3.0) - 2026-06-06

### Added

- **Web search now works on Claude (Anthropic)** — with citations, alongside OpenAI, Google, and xAI.
- **Web fetch on Claude** — have it read a specific page.
- **Limit web search to specific sites** (or block sites).
- **Web search sources are saved** — citations are stored on the action that produced them (with persistence enabled).
- **Ask which tools a provider supports** — `ProviderToolRegistry::forProvider('openai')`.
- Pass any extra provider option straight to a tool — even ones not listed yet.

### Fixed

- Web search on OpenAI/xAI was sending unsupported fields and failing — fixed.
- Code interpreter on OpenAI now works out of the box.
- Agents no longer stall when a provider keeps searching server-side mid-reply.

### Migration

This release adds one column (to store web-search citations). Atlas migrations are
publish-based, so pull the new file in first, then migrate:

```bash
php artisan vendor:publish --tag=atlas-migrations
php artisan migrate
```

Publish the new migration and run your migration to add the new column. No other breaking changes.

---

## [v3.2.0](https://github.com/atlas-php/atlas/releases/tag/v3.2.0) - 2026-06-02

### Added

- Sub-agents: one agent can now hand off work to another. List an agent in another agent's `tools()` and it can delegate a task to that specialist, which runs on its own and returns its answer — the same way any tool call works (including in the UI).
- With persistence enabled, each delegation is tracked: you can see which agent called which, and the token cost of every agent individually as well as the whole chain.

### Migration

Run `php artisan migrate` to add the new tracking columns. No other changes needed — drop-in upgrade.

---

## [v3.1.4](https://github.com/atlas-php/atlas/releases/tag/v3.1.4) - 2026-06-02

### Fixed

- OpenAI image responses that return base64 data can now be saved, stored, and auto-persisted. The base64 payload was previously mistaken for a URL, so storing the image failed; it is now decoded correctly. URL-based responses are unaffected.

### Migration

No breaking changes — drop-in upgrade. No consumer action required.

---

## [v3.1.3](https://github.com/atlas-php/atlas/releases/tag/v3.1.3) - 2026-05-24

### Fixed

- Multi-tenant (database-per-tenant) support: queued jobs no longer collide across tenants that share the same row IDs — one tenant's job could previously suppress another's. Job lock keys are now scoped per database.

### Migration

**No breaking changes.** Drop-in upgrade.

---

## [v3.1.2](https://github.com/atlas-php/atlas/releases/tag/v3.1.2) - 2026-05-16

### Fixed

- Chunkable saves wrapped in `DB::transaction()` no longer abort with SQLSTATE 25P02 on Postgres + database cache. Verified across redis, file, array, and database cache drivers.
- Rolled-back saves no longer queue a job for content that never persisted.

### Migration

**No breaking changes.**

---

## [v3.1.1](https://github.com/atlas-php/atlas/releases/tag/v3.1.1) - 2026-05-16

### Added

- Dispatch-on-save chunked indexing — edits embed within seconds, no polling. Opt out: `atlas.embeddings.dispatch_on_save = false`.
- `atlas:prune-chunks` — split the orphan scan onto its own (daily) schedule.
- `atlas:chunk --skip-orphans` flag for when running prune separately.

### Changed

- Rapid edits to the same record collapse into one queued job and debounce until `sweep_settle` seconds after the last save (`ShouldBeUnique`).
- Recommended `atlas:chunk` cadence: `hourly()` (was every-minute). It's a safety net now.

### Migration

**No breaking changes.** 

Existing schedules keep working. Recommended:

```php
$schedule->command('atlas:chunk')->hourly()->withoutOverlapping();
$schedule->command('atlas:prune-chunks')->daily()->withoutOverlapping();
```

---

## [v3.1.0](https://github.com/atlas-php/atlas/releases/tag/v3.1.0) - 2026-05-12

### Added

- **Chunked embeddings** — index long-form content; edits only re-embed the chunks that changed. Add `HasChunkedEmbeddings` to a model and schedule `atlas:chunk`.
- **`Atlas::similaritySearch()`** facade — one call for both whole-record and chunked embeddings.
- `Chunkable` and `VectorEmbeddable` interfaces.
- `atlas:chunk` and `atlas:rechunk {class}` artisan commands.

### Changed

- The `SimilaritySearch` agent tool now works against both whole-record and chunked embeddings.

### Fixed

- `HasVectorEmbeddings` no longer requires `atlas.persistence.enabled` — using the trait is enough to opt in to auto-embedding on save.
- `SimilaritySearch::usingModel()` now picks up a `VectorEmbeddable` model's `embeddable()['column']` automatically, even when `embedProvider` or `embedModel` is set.

### Migration

Re-publish and run package migrations to add the new `atlas_chunks` table:

```bash
php artisan vendor:publish --tag=atlas-migrations --force
php artisan migrate
```

`--force` overwrites any previously-published Atlas migration files in your application — back up local edits to those files first if you've customized them. Only the new `atlas_chunks` migration is required for v3.1; the rest are unchanged.

To use `Atlas::similaritySearch()` with existing `HasVectorEmbeddings` models, add `implements VectorEmbeddable` to the model class.

If you previously relied on `atlas.persistence.enabled = false` to suppress auto-embedding on `HasVectorEmbeddings` models, add `protected bool $autoEmbed = false;` to those models — the gate has been removed and the trait now generates embeddings on save unconditionally.

If you're adopting chunked embeddings, optional knobs are available under `config/atlas.php`:

```php
'embeddings' => [
    'dimensions'    => 1536,
    'chunker'       => \Atlasphp\Atlas\Embeddings\Chunkers\MarkdownChunker::class,
    'chunk_size'    => 512,   // soft cap per chunk, in tokens
    'chunk_overlap' => 50,    // tokens of overlap between adjacent chunks
    'sweep_batch'   => 50,    // rows per sweep run
    'sweep_settle'  => 60,    // seconds since updated_at before a dirty row is eligible
    'max_failures'  => 5,     // attempts before a row is excluded from sweeps
],
```

---

## [v3.0.3](https://github.com/atlas-php/atlas/releases/tag/v3.0.3) - 2026-05-12

### Added

- `StructuredResponse` now implements `JsonSerializable` and exposes `toArray()` for direct JSON encoding.
- New `atlas.persistence.connection` config (env: `ATLAS_DB_CONNECTION`) routes Atlas persistence tables to a separate database connection.

### Fixed

- Anthropic native tools (web search, etc.) now flow through as tool calls — `server_tool_use` blocks were previously dropped.
- Anthropic `pause_turn` stop reason now signals "more tool work" instead of ending the agent loop early.

### Migration

No breaking changes — drop-in upgrade. No consumer action required.

---

## [v3.0.2](https://github.com/atlas-php/atlas/releases/tag/v3.0.2) - 2026-05-06

### Fixed

- Improved Google Gemini tool-call compatibility ([#30](https://github.com/atlas-php/atlas/pull/30) by @ianfortier)

### New Contributors

- @ianfortier made their first contribution in [#30](https://github.com/atlas-php/atlas/pull/30)

**Full Changelog:** [v3.0.1...v3.0.2](https://github.com/atlas-php/atlas/compare/v3.0.1...v3.0.2)

---

## [v3.0.1](https://github.com/atlas-php/atlas/releases/tag/v3.0.1) - 2026-04-14

### Changed

- Replaced unmaintained `textalk/websocket ^1.6` with its maintained successor `phrity/websocket ^3.0` (same author, same `WebSocket\Client` namespace). Unblocks `psr/http-message ^2.0` in consuming apps, which was previously forced to `^1.0` by the old dependency.

### Internal

- `WebSocketConnection` now unwraps `$client->receive()` via `Message::getContent()` (v3 returns `Message`, v1 returned a stringable).
- `ConnectionTimeoutException` replaces `TimeoutException` in receive-path error handling.
- Voice handler `Client` construction switched from the removed `['headers' => [...]]` option to `Client::addHeader()` calls.
- All 2548 tests pass; no public API changes for consumers.

---

## [v2.5.1](https://github.com/atlas-php/atlas/releases/tag/v2.5.1) - 2026-03-17

### Changed

- Model listing now returns `list<string>` instead of `list<array{id, name}>` — simpler output, no more empty `name` fields
- Fixed provider support table (ElevenLabs has a models endpoint; Perplexity/VoyageAI do not)

---

## [v2.5.0](https://github.com/atlas-php/atlas/releases/tag/v2.5.0) - 2026-03-16

### Provider Model Listing

List available models from any AI provider — the first PHP AI SDK to ship this.

```php
Atlas::models('openai')->all();       // cached list of models
Atlas::models('openai')->refresh();   // force fresh from API
Atlas::models('openai')->clear();     // clear cache
Atlas::models('openai')->has();       // check if provider supports listing
```

- 10 of 13 Prism providers supported (Perplexity, VoyageAI, Z have no models endpoint)
- Automatic caching with configurable TTL, store, and enable/disable
- Ollama fallback from `/v1/models` to native `/api/tags`
- Sandbox `atlas:models` command with `--all`, `--refresh`, `--clear` flags

### Embedding Defaults & Caching

- Default provider/model config — `->using()` no longer required on every call
- Built-in `CacheEmbeddings` pipeline middleware with per-request overrides
- Sandbox `--cache` and `--cache-demo` flags on `atlas:embed`

### Other

- Updated Prism to v0.99.22 (new providers, GPT-5 reasoning, multimodal embeddings)
- Updated Laravel Pint to v1.29.0
- 66 new tests

### Breaking Changes

None.
