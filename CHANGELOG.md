# Changelog

All notable changes to Atlas will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com), and this project adheres to [Semantic Versioning](https://semver.org).

[All Releases](https://github.com/atlas-php/atlas/releases)

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
