# prd-drafts/ — proposals, isolated (catalog)

Draft PRDs not yet approved; a `../prd/` contract may never cite one (`doc-lint` enforces the isolation). **To write or modify one, follow [`../guides/docs-prd.md`](../guides/docs-prd.md).**

These drafts transcribe the product's guarantees from the consumer docs (the source of truth) and back each
with a real test. They await owner ratification file by file: on approval a file is `git mv`d into `../prd/`,
its IDs carrying over, and the drafts row here is removed. Ordering below follows the component ladder
(`base- → provider- → modality- → flow-`).

## Drafts — maintained by hand

Add a row when you add a draft; `doc-lint` fails the build if one is missing.

| Draft | Proposes | Reserved namespace |
|---|---|---|
| [base-configuration.md](base-configuration.md) | The unified surface, defaults, and settings that pick a provider and model | CONFIG |
| [base-transport.md](base-transport.md) | One shared outbound connection with retries, backoff, and a correlation identifier | TRANSPORT |
| [base-errors.md](base-errors.md) | A typed, catchable error taxonomy carrying provider context | ERROR |
| [base-messages.md](base-messages.md) | Typed message roles and multimodal inputs from many sources | MESSAGE |
| [base-responses.md](base-responses.md) | A normalized result per modality that reports token usage and finish reason | RESPONSE |
| [base-testing.md](base-testing.md) | A fakeable provider layer for deterministic tests without keys | TESTING |
| [provider-registry.md](provider-registry.md) | Provider resolution, defaults, and adding endpoints or custom drivers by config | PROVIDER |
| [provider-capabilities.md](provider-capabilities.md) | Declared per-provider capabilities and up-front refusal of unsupported use | CAPABILITY |
| [provider-discovery.md](provider-discovery.md) | Runtime model/voice listing, key validation, and capability inspection | DISCOVERY |
| [provider-native-tools.md](provider-native-tools.md) | Provider-hosted tools (search, fetch, file, code) run server-side with citations | PTOOL |
| [provider-failover.md](provider-failover.md) | An ordered fallback chain across providers (largely unbuilt — proposal) | FAILOVER |
| [modality-text.md](modality-text.md) | Text generation with finish reason, vision input, and provider passthrough | TEXT |
| [modality-structured-output.md](modality-structured-output.md) | Schema-validated structured data from a PHP-native schema | STRUCT |
| [modality-reasoning.md](modality-reasoning.md) | Portable extended thinking, configured once and replayed across steps | REASON |
| [modality-prompt-caching.md](modality-prompt-caching.md) | Default prompt caching with reported savings and a per-call switch | CACHE |
| [modality-token-counting.md](modality-token-counting.md) | Pre-flight input token counting including tools and media | COUNT |
| [modality-image.md](modality-image.md) | Image generation, vision understanding, and editing | IMAGE |
| [modality-speech.md](modality-speech.md) | Text-to-speech and transcription | SPEECH |
| [modality-music.md](modality-music.md) | Music generation from a prompt | MUSIC |
| [modality-sound-effects.md](modality-sound-effects.md) | Sound-effect generation from a prompt | SFX |
| [modality-video.md](modality-video.md) | Video generation and understanding, awaited to completion | VIDEO |
| [modality-voice.md](modality-voice.md) | Realtime two-way voice with server-side tools and persisted transcripts | VOICE |
| [modality-embeddings.md](modality-embeddings.md) | Text-to-vector embedding with a single source of vector size | EMBED |
| [modality-reranking.md](modality-reranking.md) | Relevance reordering of documents against a query with filters | RERANK |
| [modality-moderation.md](modality-moderation.md) | Unsafe-content flagging with per-category verdicts | MODERATE |
| [modality-batch.md](modality-batch.md) | Deferred bulk jobs at reduced cost with keyed results and refusals | BATCH |
| [flow-tool-loop.md](flow-tool-loop.md) | The multi-step tool-calling loop with typed tools and concurrency | TOOLLOOP |
| [flow-agents.md](flow-agents.md) | Reusable agents bundling provider, model, instructions, and tools | AGENT |
| [flow-sub-agents.md](flow-sub-agents.md) | Delegation to other agents with isolation, fan-out, and guards | SUBAGENT |
| [flow-streaming.md](flow-streaming.md) | Piece-by-piece replies over a live stream and broadcast, error-safe | STREAM |
| [flow-middleware.md](flow-middleware.md) | Auto-routed hooks at the agent, step, tool, and provider layers | MIDDLEWARE |
| [flow-conversations.md](flow-conversations.md) | Cross-turn memory with multi-user, multi-agent threads and media replay | CONVO |
| [flow-execution-tracking.md](flow-execution-tracking.md) | Auditable record of every call, step, tool, and the delegation tree | EXEC |
| [flow-retry-branch.md](flow-retry-branch.md) | Regenerating replies and stepping through their versions | RETRY |
| [flow-media-assets.md](flow-media-assets.md) | Generated media stored to disk and linked to what produced it | ASSET |
| [flow-similarity-search.md](flow-similarity-search.md) | Meaning-based record search, whole-record or chunked, through one call | SEARCH |
| [flow-queue.md](flow-queue.md) | Background execution with an immediate handle and callbacks | QUEUE |
| [flow-events.md](flow-events.md) | Observe-only lifecycle events with correlation and deterministic ordering | EVENT |
