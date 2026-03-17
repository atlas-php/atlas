# Changelog

All notable changes to Atlas will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com), and this project adheres to [Semantic Versioning](https://semver.org).

[All Releases](https://github.com/atlas-php/atlas/releases)

---

## [v2.5.0](https://github.com/atlas-php/atlas/releases/tag/v2.5.0) - 2026-03-16

### Added

- Built-in `CacheEmbeddings` pipeline middleware with global config and per-request overrides via metadata (`cache`, `cache_store`, `cache_ttl`, `cache_key`)
- Default embedding provider/model config (`atlas.embeddings.provider`, `atlas.embeddings.model`) — `->using()` no longer required on every call
- `env()` helpers for all embedding config values (`ATLAS_EMBEDDING_PROVIDER`, `ATLAS_EMBEDDING_MODEL`, `ATLAS_EMBEDDING_CACHE_ENABLED`, `ATLAS_EMBEDDING_CACHE_STORE`, `ATLAS_EMBEDDING_CACHE_TTL`)
- Explicit `AtlasManager::embeddings()` method that applies config defaults before delegating to Prism
- Auto-registration of `CacheEmbeddings` middleware in `AtlasServiceProvider` when `embeddings.cache.enabled` is true (priority 100)
- Sandbox `--cache` and `--cache-demo` flags on `atlas:embed` command
- 31 new tests: `CacheEmbeddings`, `AtlasManager::embeddings()`, service provider cache registration, `Atlas::fake()`/`unfake()`/`getFake()`/`isFaked()`, `FakeAgentExecutor::setRealExecutor()`/`respondUsing()`, `AbstractExtensionRegistry`

### Changed

- Updated Prism from v0.99.21 to v0.99.22 (Perplexity, Z.AI providers, GPT-5 reasoning, multimodal embeddings for VoyageAI/Gemini, tool/OpenRouter fixes)
- Updated Laravel Pint from v1.27.0 to v1.29.0 and applied formatting across codebase
- Added `--memory-limit=512M` to PHPStan composer script
- Sandbox `atlas:embed` now uses config defaults instead of hardcoded `->using()` calls
- Updated embeddings and pipelines documentation to reflect built-in caching

### Breaking Changes

None. All changes are additive.
