# Codemap — atlas

> As of 2026-07-20, branch `3.x`, CHANGELOG v3.6.1. Re-verify counts against the current branch HEAD if this date is stale.

The always-loaded structural map: *where things are*, layer by layer. `Atlasphp\Atlas` is a unified AI SDK for Laravel — PHP 8.2+, Laravel 11/12/13 package that owns its own provider layer (no external AI SDK). Providers: OpenAI, Anthropic, Google Gemini, xAI, ElevenLabs (voice/audio), Cohere + Jina (rerank); the `ChatCompletions/` driver covers OpenAI-compatible endpoints (Ollama, LM Studio). Runtime flow: Executor → Driver → Handlers + Resolvers → HttpClient.

## Entry Points (src/ root)

- **Atlas** — the facade (`class Atlas extends Facade`, namespace `Atlasphp\Atlas`; carries the `@method static` map). **No `src/Facades/` dir exists** despite the composer alias pointing at `Atlasphp\Atlas\Facades\Atlas`.
- **AtlasManager** — manager/orchestrator behind the facade (facade accessor).
- **AtlasServiceProvider** — bootstrap: voice route registration, agent + chunkable auto-discovery, config + migrations publish.
- **AtlasConfig**, **RequestConfig** — config DTOs. **AtlasCache** — model/voice/embedding cache. **Agent**, **AgentRegistry** — agent definition + registry.
- **Facade methods** — `Atlas::` `text()` `image()` `audio()` `music()` `sfx()` `speech()` `video()` `embed()` `moderate()` `voice()` `rerank()` `batch()` `batchGroup()` `provider()` `agent()` `providers()` `registerChunkable()` `chunkables()` `similaritySearch()` `fake()`.

## Enums (src/Enums/ — 11)

BatchResultStatus, BatchStatus, ChunkType, FinishReason, Modality, Provider, ReasoningEffort (Minimal/Low/Medium/High), Role, ToolChoiceMode, TurnDetectionMode, VoiceTransport.

## Messages (src/Messages/ — 6)

Message (base), UserMessage, AssistantMessage, SystemMessage, ToolCall, ToolResultMessage.

## Pending Builders (src/Pending/ — 15)

Fluent builders returned by the facade: TextRequest, ImageRequest, AudioRequest, VideoRequest, SpeechRequest, MusicRequest, SfxRequest, EmbedRequest, ModerateRequest, RerankRequest, VoiceRequest, AgentRequest, BatchRequest (`add()`/`addMany()`/`group()`/`completionWindow()`/`submit()`), GenerativeAudioRequest, ProviderRequest (base).

- **Concerns/ (9)** — ConvertsResultToChunks, HasMeta, HasMiddleware, HasProviderOptions, HasQueueDispatch, HasRequestConfig, HasVariables, NormalizesMessages, ResolvesProvider.
- **Contracts/ (1)** — Batchable (the contract BatchRequest accepts).

## Requests — DTOs (src/Requests/ — 11)

Immutable request DTOs (names mirror `Pending/` builders): AudioRequest, Batch (+ BatchLine), EmbedRequest, ImageRequest, ModerateRequest, Reasoning (`budgetTokens()`; threaded into TextRequest as `?Reasoning $reasoning`), RerankRequest, TextRequest, VideoRequest, VoiceRequest.

## Responses (src/Responses/ — 18)

TextResponse, StreamResponse, StructuredResponse, ImageResponse, AudioResponse, VideoResponse, RerankResponse (+ RerankResult), EmbeddingsResponse, ModerationResponse, BatchResponse (+ BatchResult, RequestCounts), Usage, TokenCount (pre-flight input-token count from `->countTokens()`), VoiceSession, VoiceEvent, StreamChunk. **Contracts/** — Storable.

## Executor (src/Executor/ — 7)

Tool loop + step orchestration: AgentExecutor, ToolExecutor, ToolRegistry, ExecutionContext, Step, ExecutorResult, ToolResult.

## Providers (src/Providers/)

- **Core (9)** — Driver, ResponsesDriver (neutral OpenAI-Responses-API driver for Ollama and other Responses-API proxies), ProviderRegistry, ProviderConfig, ProviderCapabilities, ModelList, VoiceList, WebSocketConnection, SseParser. (HttpClient + RetryDecider live in `src/Http/`.)
- **Responses/** — shared OpenAI Responses API resolver set: Handlers/Text, MediaResolver, ResponseParser, ToolMapper. Composed by both `OpenAiDriver` and `ResponsesDriver`.
- **Handlers/ (12)** — modality interfaces/abstracts: AbstractProviderHandler, AbstractRerankHandler, ProviderHandler, TextHandler, ImageHandler, AudioHandler, VideoHandler, ModerateHandler, EmbedHandler, RerankHandler, VoiceHandler, BatchHandler.
- **Contracts/ (5)** — resolver seams: MessageFactoryContract, ResponseParserContract, ToolMapperContract, MediaResolverContract, ProviderRegistryContract.
- **Concerns/ (7)** — AppliesToolChoice, BuildsHeaders, BuildsResponsesMessages, BuildsVoiceBody, CountsTokens (heuristic estimate where no native count endpoint), ResolvesAudioFile, ResolvesMediaUri.
- **Tools/ (9)** — provider-native tools: ProviderTool (base), ProviderToolRegistry, CodeExecution, CodeInterpreter, FileSearch, GoogleSearch, WebFetch, WebSearch, XSearch.

### Per-vendor drivers (src/Providers/{Vendor}/ — 8)

| Vendor | Driver + parts | Handlers |
|---|---|---|
| OpenAi | OpenAiDriver (Responses API), MessageFactory, Concerns/HasOrganizationHeader (resolvers from shared `Responses/`) | Audio, Batch, Embed, Image, Moderate, Provider, Text, Video, Voice |
| Anthropic | AnthropicDriver, MediaResolver, MessageFactory, ResponseParser, ToolMapper, Concerns/BuildsAnthropicHeaders | Batch, Provider, Text |
| Google | GoogleDriver, GoogleToolCall, MediaResolver, MessageFactory, ResponseParser, ToolMapper, Concerns/BuildsGoogleHeaders | Batch, Embed, Image, Provider, Text |
| Xai | XaiDriver, MessageFactory, ResponseParser, ToolMapper | Audio, Image, Provider, Text, Video, Voice |
| ChatCompletions | ChatCompletionsDriver, MediaResolver, MessageFactory, ResponseParser, ToolMapper (Ollama, LM Studio) | Provider, Text |
| ElevenLabs | ElevenLabsDriver, Concerns/BuildsElevenLabsHeaders | Audio, Music, Provider, Sfx, Voice |
| Cohere | CohereDriver | CohereRerankHandler |
| Jina | JinaDriver | JinaRerankHandler |

## Persistence (src/Persistence/)

Root: ProcessQueuedMessage, ToolAssets. Model services (`Services/`) are the single point of truth for Eloquent access; domain services orchestrate.

- **Models/ (12)**

| Model | Notes |
|---|---|
| Asset, ConversationMessageAsset | media assets + message join |
| Conversation, ConversationMessage | conversation persistence |
| Execution | sub-agent lineage (`parent_execution_id`/`parent_tool_call_id`/`depth`, `totalUsage()` subtree roll-up) |
| ExecutionStep, ExecutionToolCall | per-step + per-tool-call records |
| BatchGroup, BatchJob | job status/counts/usage (`open()` scope, `applyStatus()`/`markCompleted()`/`markFailed()`) |
| BatchResult | per-line, unique `(batch_job_id, custom_id)` |
| Chunk | embedding chunk rows |
| VoiceCall | voice session records |

- **Services/ (5)** — ConversationService, ExecutionService, ChunkContentService, ChunkSearchService, RecordSearchService.
- **Middleware/ (5)** — PersistConversation, TrackExecution, TrackProviderCall, TrackStep, TrackToolCall.
- **Concerns/ (7)** — consumer-app model traits: HasAtlasTable, HasChunkedEmbeddings, HasConversations, HasExecutionStatus, HasOwner, HasVectorEmbeddings, ResolvesChunkModel.
- **Enums/ (7)** — AssetType, ExecutionStatus, ExecutionType, MessageRole, MessageStatus, ToolCallType, VoiceCallStatus.
- **Misc** — Schema/ChunkedEmbeddingColumns, Support/MimeTypeMap, Http/StoreVoiceTranscriptController.

## Embeddings (src/Embeddings/ — 7)

EmbeddingResolver, VectorQueryMacros, Chunkable, ChunkableRegistry, ChunkData, SearchResult, VectorEmbeddable. **Chunkers/ (3)** — Chunker (base), BaseTokenAwareChunker, MarkdownChunker.

## Voice (src/Voice/Http/ — 2)

VoiceToolController, CloseVoiceSessionController. VoiceSession + VoiceEvent live under `Responses/`; the `BuildsVoiceBody` trait (session body + `https:`→`wss:` rewrite) is in `Providers/Concerns/`, shared by OpenAI + xAI voice handlers.

## Queue (src/Queue/)

PendingExecution. **Contracts/** — QueueableRequest. **Jobs/ (3)** — ExecuteAtlasJob, ChunkContentJob, TracksExecution.

## Middleware (src/Middleware/ — 6)

ProviderContext, ToolContext, StepContext, AgentContext, MiddlewareStack, MiddlewareResolver.

- **Contracts/ (11)** — marker interfaces routed by type: AgentMiddleware, AudioMiddleware, EmbedMiddleware, ImageMiddleware, ProviderMiddleware, StepMiddleware, TextMiddleware, ToolMiddleware, VideoMiddleware, VoiceMiddleware, VoiceHttpMiddleware.

## Tools — infrastructure (src/Tools/ — 6)

Tool, ToolDefinition, ToolSerializer, ToolChoice, SimilaritySearch, AgentTool (wraps an Agent as a sub-agent delegation tool; `Tool::isDelegation()` flag).

## Schema (src/Schema/ — 3)

SchemaBuilder, Schema, StrictSchema (normalizes JSON Schema to OpenAI strict form: recursive `additionalProperties:false` + all-required, optionals → nullable). **Fields/ (9)** — Field (base), StringField, IntegerField, NumberField, BooleanField, ArrayField, ObjectField, ObjectFieldBuilder, EnumField.

## Events (src/Events/ — 40, + 3 Concerns/)

- **Agent (8)** — AgentStarted, AgentStepStarted, AgentStepCompleted, AgentCompleted, AgentMaxStepsExceeded, AgentToolCallStarted, AgentToolCallCompleted, AgentToolCallFailed.
- **Execution (5)** — ExecutionEvent (base), ExecutionQueued, ExecutionProcessing, ExecutionCompleted, ExecutionFailed. **Modality (2)** — ModalityStarted, ModalityCompleted.
- **Provider (4)** — ProviderRequestStarted, ProviderRequestCompleted, ProviderRequestFailed, ProviderRequestRetrying.
- **Streaming (5)** — StreamStarted, StreamCompleted, StreamChunkReceived, StreamThinkingReceived, StreamToolCallReceived.
- **Voice (9)** — VoiceSessionCreated, VoiceSessionEnded, VoiceCallStarted, VoiceCallCompleted, VoiceToolCallStarted, VoiceToolCallCompleted, VoiceToolCallFailed, VoiceAudioDeltaReceived, VoiceTranscriptDeltaReceived.
- **Chunking/Conversation (3)** — ContentChunked, ContentChunkingFailed, ConversationMessageStored. **Batch (4)** — BatchSubmitted, BatchCompleted, BatchFailed, BatchGroupCompleted.
- **Concerns/ (3)** — BroadcastsOnChannel, BroadcastsOnOptionalChannel, CapsBroadcastPayload.

## Exceptions (src/Exceptions/ — 17)

AtlasException (base), ProviderException, AuthenticationException, AuthorizationException, RateLimitException, ProviderNotFoundException, AgentNotFoundException, ToolNotFoundException, MaxStepsExceededException, UnsupportedFeatureException, MaxDelegationDepthException, DelegationCycleException, BatchException, ConnectionException, InvalidRequestException, ModelNotFoundException, ServerException.

## Support layers

- **Input/ (5)** — Input (base), Image, Audio, Video, Document.
- **Console/ (9)** — MakeAgentCommand, MakeToolCommand, MiddlewareCommand, CleanStaleVoiceSessionsCommand, ChunkCommand, RechunkCommand, PruneChunksCommand, PollBatchJobsCommand, PruneBatchJobsCommand.
- **Batch/ (1)** — BatchService (domain orchestration: `submitAndTrack()` + `syncFromProvider()` in a transaction; shared by the poll command). Batch = deferred provider jobs at ~50% cost.
- **Support/ (3)** — VariableRegistry, VariableInterpolator, TokenCounter (pure utilities).
- **Concerns/ (2)** — StoresMedia, ResolvesDatabaseScope (multi-tenant scoping for unique job locks).
- **Http/ (3)** — HttpClient, RetryDecider, ProviderRequestContext (shared transport; stamps a correlation id onto `ProviderRequest*` events).

## Testing Fakes (src/Testing/ — 13)

AtlasFake, **FakeDriver** (skips the base `Driver` constructor, so `Driver::$config` is never set — resolves attribution from its own name), RecordedRequest, plus response fakes: TextResponseFake, ImageResponseFake, AudioResponseFake, VideoResponseFake, EmbeddingsResponseFake, ModerationResponseFake, RerankResponseFake, StreamResponseFake, StructuredResponseFake, VoiceSessionFake.

## Config & Routes

- `config/atlas.php` — keys: `defaults`, `agents`, `prompt_cache`, `providers`, `retry`, `queue`, `batch`, `stream`, `broadcast`, `middleware`, `variables`, `storage`, `embeddings`, `cache`, `persistence`, `voice`. Real values live in `.env` / config, not here.
- **Routes** — registered programmatically by `AtlasServiceProvider::registerVoiceRoutes()` in `boot()`, prefix from `voice.route_prefix` (default `atlas`): `POST {prefix}/voice/{sessionId}/tool` → VoiceToolController · `/close` → CloseVoiceSessionController · `/transcript` → StoreVoiceTranscriptController.

## Database (database/ — 19 migrations, 7 factories)

`migrations/` — `atlas_`-prefixed tables: conversations, conversation_messages, assets, voice_calls, executions, execution_steps, execution_tool_calls, chunks, batch jobs/results, plus FK-add migrations. `factories/` — Asset, Conversation, ConversationMessage, ConversationMessageAsset, Execution, ExecutionStep, ExecutionToolCall.

## Tests (tests/ — Pest)

- **Feature/** — Console, Persistence, Testing, Variables, Voice + top-level entry-point / facade / config / token-count tests.
- **Unit/** — mirrors `src/` per domain (Embeddings/Chunkers, Persistence subdirs, Providers per-vendor + Concerns/Tools/Responses, Streaming, etc.). **Fixtures/**.

## Sandbox & CI

- `sandbox/` — real-API test harness (Laravel app shell, `bootstrap.php`); Horizon must run for queue-backed features. Provider smoke tests, feature/reasoning/batch/embeddings scripts, `seed-demo.php`.
- `.github/workflows/` — `tests.yml` (CI), `deploy-docs.yml`, `doc-lint.yml` (knowledge-doc lint). `composer check` = lint:test (Pint) + analyse (PHPStan) + test (Pest) + lint:docs (doc-lint).

## Docs

- `.knowledge/` — the agent-facing documentation system and home of the always-loaded orientation trio (BRIEF/CODEMAP/MEMORY) + OVERVIEW, plus prd/, prd-drafts/, research/, references/, guides/. See `.knowledge/README.md`.
- `docs/` — consumer-facing VitePress site (atlasphp.org): getting-started, modalities/capabilities, features, guides, advanced.

---
*Editing this file? Follow the standard first: [`guides/docs-codemap.md`](./guides/docs-codemap.md).*
