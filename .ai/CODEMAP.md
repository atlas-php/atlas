# Codemap - atlas

> As of 2026-06-30, branch `3.x`, HEAD `3a22f27`, CHANGELOG v3.6.0. Re-verify counts against the current branch HEAD if this date is stale.

`Atlasphp\Atlas` — unified AI SDK for Laravel. PHP 8.2+, Laravel 11/12/13 package. Owns its own provider layer (no external AI SDK dependency). Integrated providers: OpenAI, Anthropic, Google Gemini, xAI, ElevenLabs (voice/audio), Cohere (rerank), Jina (rerank); the shared `ChatCompletions/` driver covers OpenAI-compatible endpoints (Ollama, LM Studio).

## Root (src/)

- **Atlas** — the facade (`class Atlas extends Facade`, namespace `Atlasphp\Atlas`; carries the `@method static` map). No `src/Facades/` dir exists.
- **AtlasManager** — manager/orchestrator (facade accessor)
- **AtlasServiceProvider** — bootstrap, voice route registration, agent + chunkable auto-discovery, config + migrations publish
- **AtlasConfig**, **RequestConfig** — config DTOs
- **AtlasCache** — model/voice/embedding cache
- **Agent**, **AgentRegistry** — agent definition + registry

## Facade methods

`Atlas::` — `text()`, `image()`, `audio()`, `music()`, `sfx()`, `speech()`, `video()`, `embed()`, `moderate()`, `voice()`, `rerank()`, `batch()`, `batchGroup()`, `provider()`, `agent()`, `providers()`, `registerChunkable()`, `chunkables()`, `similaritySearch()`, `fake()`

## Enums (src/Enums/ — 11)

BatchResultStatus, BatchStatus, ChunkType, FinishReason, Modality, Provider, ReasoningEffort (Minimal/Low/Medium/High), Role, ToolChoiceMode, TurnDetectionMode, VoiceTransport

## Messages (src/Messages/ — 6)

Message (base), UserMessage, AssistantMessage, SystemMessage, ToolCall, ToolResultMessage

## Pending Builders (src/Pending/ — 15)

Fluent builders returned by the facade.

- **Request types:** TextRequest, ImageRequest, AudioRequest, VideoRequest, SpeechRequest, MusicRequest, SfxRequest, EmbedRequest, ModerateRequest, RerankRequest, VoiceRequest, AgentRequest, BatchRequest (`add()`/`addMany()`/`group()`/`completionWindow()`/`submit()`), GenerativeAudioRequest, ProviderRequest (base)
- **Concerns/ (9):** ConvertsResultToChunks, HasMeta, HasMiddleware, HasProviderOptions, HasQueueDispatch, HasRequestConfig, HasVariables, NormalizesMessages, ResolvesProvider
- **Contracts/:** Batchable (the contract BatchRequest accepts)

## Requests — DTOs (src/Requests/ — 11)

Immutable request DTOs (class names mirror the `Pending/` builders): AudioRequest, Batch (+ BatchLine), EmbedRequest, ImageRequest, ModerateRequest, Reasoning (`budgetTokens()`; threaded into TextRequest as `?Reasoning $reasoning`), RerankRequest, TextRequest, VideoRequest, VoiceRequest

## Responses (src/Responses/ — 18)

- TextResponse, StreamResponse, StructuredResponse, ImageResponse, AudioResponse, VideoResponse, RerankResponse (+ RerankResult), EmbeddingsResponse, ModerationResponse, BatchResponse (+ BatchResult, RequestCounts), Usage, TokenCount (pre-flight input-token count from `->countTokens()`), VoiceSession, VoiceEvent, StreamChunk
- **Contracts/** — Storable (interface)

## Executor (src/Executor/ — 7)

Tool loop + step orchestration: AgentExecutor, ToolExecutor, ToolRegistry, ExecutionContext, Step, ExecutorResult, ToolResult

## Providers (src/Providers/)

Provider layer (driver → handlers + resolvers).

- **Core:** Driver, ResponsesDriver (neutral OpenAI-Responses-API driver — Responses text handler with no org header + shared image/audio/video/embed/moderate handlers; for Ollama and other Responses-API proxies), ProviderRegistry, ProviderConfig, ProviderCapabilities, ModelList, VoiceList, WebSocketConnection, SseParser (HttpClient + RetryDecider live in `src/Http/`)
- **Responses/** — shared OpenAI Responses API resolver set: Handlers/Text, MediaResolver, ResponseParser, ToolMapper. Composed by both `OpenAiDriver` and `ResponsesDriver`, so `OpenAi/` carries no own MediaResolver/ResponseParser/ToolMapper.
- **Handlers/ (12)** — modality handler interfaces/abstracts: AbstractProviderHandler, AbstractRerankHandler, ProviderHandler, TextHandler, ImageHandler, AudioHandler, VideoHandler, ModerateHandler, EmbedHandler, RerankHandler, VoiceHandler, BatchHandler
- **Contracts/ (5)** — resolver seams: MessageFactoryContract, ResponseParserContract, ToolMapperContract, MediaResolverContract, ProviderRegistryContract
- **Concerns/ (7)** — shared provider traits: AppliesToolChoice, BuildsHeaders, BuildsResponsesMessages, BuildsVoiceBody, CountsTokens (heuristic estimate for providers without a native count endpoint), ResolvesAudioFile, ResolvesMediaUri
- **Tools/ (9)** — provider-native tools: ProviderTool (base), ProviderToolRegistry, CodeExecution, CodeInterpreter, FileSearch, GoogleSearch, WebFetch, WebSearch, XSearch
- **Per-vendor:**
  - **OpenAi/** — OpenAiDriver (extends Driver, Responses API); MessageFactory; Concerns/HasOrganizationHeader; Handlers: Audio, Batch, Embed, Image, Moderate, Provider, Text, Video, Voice (resolvers from shared `Responses/`)
  - **Anthropic/** — AnthropicDriver; MediaResolver, MessageFactory, ResponseParser, ToolMapper; Concerns/BuildsAnthropicHeaders; Handlers: Batch, Provider, Text
  - **Google/** — GoogleDriver, GoogleToolCall; MediaResolver, MessageFactory, ResponseParser, ToolMapper; Concerns/BuildsGoogleHeaders; Handlers: Batch, Embed, Image, Provider, Text
  - **Xai/** — XaiDriver, MessageFactory, ResponseParser, ToolMapper; Handlers: Audio, Image, Provider, Text, Video, Voice
  - **ChatCompletions/** — ChatCompletionsDriver; MediaResolver, MessageFactory, ResponseParser, ToolMapper; Handlers: Provider, Text (OpenAI-compatible: Ollama, LM Studio)
  - **ElevenLabs/** — ElevenLabsDriver; Concerns/BuildsElevenLabsHeaders; Handlers: Audio, Music, Provider, Sfx, Voice
  - **Cohere/** — CohereDriver, CohereRerankHandler
  - **Jina/** — JinaDriver, JinaRerankHandler

## Persistence (src/Persistence/)

- Root: ProcessQueuedMessage, ToolAssets
- **Models/ (12)** — Asset, BatchGroup, BatchJob (status/counts/usage + `open()` scope + `applyStatus()`/`markCompleted()`/`markFailed()`), BatchResult (per-line, unique `(batch_job_id, custom_id)`), Chunk, Conversation, ConversationMessage, ConversationMessageAsset, Execution (sub-agent lineage: `parent_execution_id`/`parent_tool_call_id`/`depth` + `parent()`/`children()`/`parentToolCall()` relations + `totalUsage()` subtree roll-up), ExecutionStep, ExecutionToolCall, VoiceCall
- **Services/ (5)** — ConversationService, ExecutionService, ChunkContentService, ChunkSearchService, RecordSearchService
- **Middleware/ (5)** — PersistConversation, TrackExecution, TrackProviderCall, TrackStep, TrackToolCall
- **Concerns/ (7)** — consumer-app model traits: HasAtlasTable, HasChunkedEmbeddings, HasConversations, HasExecutionStatus, HasOwner, HasVectorEmbeddings, ResolvesChunkModel
- **Enums/ (7)** — AssetType, ExecutionStatus, ExecutionType, MessageRole, MessageStatus, ToolCallType, VoiceCallStatus
- **Schema/** — ChunkedEmbeddingColumns
- **Support/** — MimeTypeMap
- **Http/** — StoreVoiceTranscriptController

## Embeddings (src/Embeddings/ — 7)

- EmbeddingResolver, VectorQueryMacros, Chunkable, ChunkableRegistry, ChunkData, SearchResult, VectorEmbeddable
- **Chunkers/ (3)** — Chunker (base), BaseTokenAwareChunker, MarkdownChunker

## Voice (src/Voice/Http/ — 2)

VoiceToolController, CloseVoiceSessionController. VoiceSession + VoiceEvent live under `Responses/`; the `BuildsVoiceBody` trait (session body + `https:` → `wss:` URL rewrite) is in `Providers/Concerns/`, shared by OpenAI and xAI voice handlers.

## Queue (src/Queue/)

- PendingExecution
- **Contracts/** — QueueableRequest
- **Jobs/ (3)** — ExecuteAtlasJob, ChunkContentJob, TracksExecution

## Middleware (src/Middleware/ — 6)

ProviderContext, ToolContext, StepContext, AgentContext, MiddlewareStack, MiddlewareResolver

- **Contracts/ (11)** — marker interfaces routed by type: AgentMiddleware, AudioMiddleware, EmbedMiddleware, ImageMiddleware, ProviderMiddleware, StepMiddleware, TextMiddleware, ToolMiddleware, VideoMiddleware, VoiceMiddleware, VoiceHttpMiddleware

## Tools — infrastructure (src/Tools/ — 6)

Tool, ToolDefinition, ToolSerializer, ToolChoice, SimilaritySearch, AgentTool (wraps an Agent as a sub-agent delegation tool; `Tool::isDelegation()` flag)

## Schema (src/Schema/ — 3)

- SchemaBuilder, Schema, StrictSchema (normalizes a JSON Schema to OpenAI strict structured-output form: recursive `additionalProperties:false` + all-required, optionals → nullable)
- **Fields/ (9)** — Field (base), StringField, IntegerField, NumberField, BooleanField, ArrayField, ObjectField, ObjectFieldBuilder, EnumField

## Events (src/Events/ — 40, + 3 Concerns/)

- **Agent:** AgentStarted, AgentStepStarted, AgentStepCompleted, AgentCompleted, AgentMaxStepsExceeded, AgentToolCallStarted, AgentToolCallCompleted, AgentToolCallFailed
- **Execution:** ExecutionEvent (base), ExecutionQueued, ExecutionProcessing, ExecutionCompleted, ExecutionFailed
- **Modality:** ModalityStarted, ModalityCompleted
- **Provider:** ProviderRequestStarted, ProviderRequestCompleted, ProviderRequestFailed, ProviderRequestRetrying
- **Streaming:** StreamStarted, StreamCompleted, StreamChunkReceived, StreamThinkingReceived, StreamToolCallReceived
- **Voice:** VoiceSessionCreated, VoiceSessionEnded, VoiceCallStarted, VoiceCallCompleted, VoiceToolCallStarted, VoiceToolCallCompleted, VoiceToolCallFailed, VoiceAudioDeltaReceived, VoiceTranscriptDeltaReceived
- **Chunking/Conversation:** ContentChunked, ContentChunkingFailed, ConversationMessageStored
- **Batch:** BatchSubmitted, BatchCompleted, BatchFailed, BatchGroupCompleted
- **Concerns/ (3):** BroadcastsOnChannel, BroadcastsOnOptionalChannel, CapsBroadcastPayload (configurable cap for broadcasted tool payloads)

## Exceptions (src/Exceptions/ — 17)

AtlasException, ProviderException, AuthenticationException, AuthorizationException, RateLimitException, ProviderNotFoundException, AgentNotFoundException, ToolNotFoundException, MaxStepsExceededException, UnsupportedFeatureException, MaxDelegationDepthException, DelegationCycleException, BatchException, ConnectionException, InvalidRequestException, ModelNotFoundException, ServerException

## Misc

- `src/Input/ (5)` — Input (base), Image, Audio, Video, Document
- `src/Console/ (9)` — MakeAgentCommand, MakeToolCommand, MiddlewareCommand, CleanStaleVoiceSessionsCommand, ChunkCommand, RechunkCommand, PruneChunksCommand, PollBatchJobsCommand, PruneBatchJobsCommand
- `src/Batch/` — BatchService (domain orchestration: `submitAndTrack()` + `syncFromProvider()` hydration in a transaction, shared by the poll command). Batch = deferred provider jobs at ~50% cost; OpenAi (text/embed) + Anthropic (text) + Google (text). `Pending\BatchRequest::submit()` tracks a BatchJob when persistence is on, else returns a stateless `BatchResponse`.
- `src/Support/ (3)` — VariableRegistry, VariableInterpolator, TokenCounter (pure utilities)
- `src/Concerns/ (2)` — StoresMedia, ResolvesDatabaseScope (multi-tenant DB scoping for unique job locks)
- `src/Http/ (3)` — HttpClient, RetryDecider, ProviderRequestContext (shared transport; context carries provider/model + a stamped correlation id onto `ProviderRequest*` events)

## Testing Fakes (src/Testing/ — 13)

- AtlasFake, FakeDriver, RecordedRequest
- Response fakes: TextResponseFake, ImageResponseFake, AudioResponseFake, VideoResponseFake, EmbeddingsResponseFake, ModerationResponseFake, RerankResponseFake, StreamResponseFake, StructuredResponseFake, VoiceSessionFake

## Config

`config/atlas.php` — keys: `defaults`, `agents`, `prompt_cache`, `providers`, `retry`, `queue`, `batch`, `stream`, `broadcast`, `middleware`, `variables`, `storage`, `embeddings`, `cache`, `persistence` (`auto_store_assets`, `message_limit`, `connection`, `table_prefix`, custom model bindings), `voice` (`route_prefix`, `route_middleware`, `session_ttl`)

## Routes

Registered programmatically by `AtlasServiceProvider::registerVoiceRoutes()` in `boot()`. Session-scoped paths:

- `POST {prefix}/voice/{sessionId}/tool` → VoiceToolController
- `POST {prefix}/voice/{sessionId}/close` → CloseVoiceSessionController
- `POST {prefix}/voice/{sessionId}/transcript` → StoreVoiceTranscriptController

Prefix from `voice.route_prefix` (default `atlas`); applies `voice.route_middleware` + `MiddlewareResolver::forVoiceHttp()`.

## Tests

`tests/` — Pest. **Feature:** Console, Persistence, Testing, Variables, Voice. **Unit:** mirrors `src/` (per-domain dirs incl. Embeddings/Chunkers, Persistence/* subdirs, Providers per-vendor + Concerns/Tools/Responses). **Fixtures/**.

## Sandbox

`sandbox/` — real-API test harness (Laravel app shell, `bootstrap.php`); Horizon must be running for queue-backed features.

- **Provider smoke tests:** `test-{openai,anthropic,google,xai,elevenlabs}-provider.php`, `test-custom-driver.php` (Ollama), `test-lmstudio-provider.php`
- **Feature tests:** `test-{agent,subagents,subagents-concurrent,conversation,middleware,streaming,tools,provider-tools-live,voice,prompt-caching,vision-replay,media-config,multitenant-job-locks,queued-message-dispatch}.php`; live/coverage: `test-{error-context-live,force-tools-live,provider-tools-coverage-live,token-counting}.php`; `seed-demo.php`
- **Reasoning:** `test-reasoning{,-forced-tools,-recording,-tools}.php`
- **Batch:** `test-batch{,-demo,-modes,-tables,-google,-google-e2e}.php`
- **Embeddings:** `test-chunked-embeddings{,-dispatch-on-save,-edge-cases}.php`, `test-embeddings-full-suite.php`, `test-record-embeddings.php`

## Documentation

`docs/` — VitePress site (atlasphp.org). Sections: getting-started, modalities/capabilities, features, guides, advanced.

## Composer Scripts

`composer check` (lint:test + analyse + test) · `lint`/`lint:test` (Pint) · `analyse` (PHPStan) · `test` (Pest)
