# Changelog

All notable changes to Atlas will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com), and this project adheres to [Semantic Versioning](https://semver.org).

[All Releases](https://github.com/atlas-php/atlas/releases)

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
