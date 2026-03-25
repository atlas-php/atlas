<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending;

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Agents\AgentRegistry;
use Atlasphp\Atlas\Concerns\HasQueueDispatch;
use Atlasphp\Atlas\Concerns\HasVariables;
use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Enums\Role;
use Atlasphp\Atlas\Events\ConversationMessageStored;
use Atlasphp\Atlas\Events\ModalityCompleted;
use Atlasphp\Atlas\Events\ModalityStarted;
use Atlasphp\Atlas\Events\VoiceCallStarted;
use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Executor\AgentExecutor;
use Atlasphp\Atlas\Executor\ExecutionContext;
use Atlasphp\Atlas\Executor\ExecutorResult;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Messages\Message;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Middleware\AgentContext;
use Atlasphp\Atlas\Middleware\MiddlewareStack;
use Atlasphp\Atlas\Pending\Concerns\HasMeta;
use Atlasphp\Atlas\Pending\Concerns\HasMiddleware;
use Atlasphp\Atlas\Pending\Concerns\HasProviderOptions;
use Atlasphp\Atlas\Pending\Concerns\NormalizesMessages;
use Atlasphp\Atlas\Persistence\Concerns\HasConversations;
use Atlasphp\Atlas\Persistence\Enums\AssetType;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Persistence\Enums\VoiceCallStatus;
use Atlasphp\Atlas\Persistence\Models\Asset;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\MessageAttachment;
use Atlasphp\Atlas\Persistence\Models\VoiceCall;
use Atlasphp\Atlas\Persistence\Services\ConversationService;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\Tools\ProviderTool;
use Atlasphp\Atlas\Queue\PendingExecution;
use Atlasphp\Atlas\Queue\QueueableRequestContract;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\VoiceSession;
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Tools\Tool;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fluent builder for agent execution requests.
 *
 * Resolves an agent by key, builds a TextRequest with variable interpolation,
 * and dispatches through the AgentExecutor (with tools) or directly to the
 * driver (without tools). Supports runtime overrides for all agent config.
 */
class AgentRequest implements QueueableRequestContract
{
    use Concerns\ConvertsResultToChunks;
    use HasMeta;
    use HasMiddleware;
    use HasProviderOptions;
    use HasQueueDispatch {
        dispatchToQueue as traitDispatchToQueue;
    }
    use HasVariables;
    use NormalizesMessages;

    // ─── Runtime overrides ──────────────────────────────────────────

    protected ?string $instructionsOverride = null;

    protected ?string $message = null;

    /** @var array<int, Input> */
    protected array $messageMedia = [];

    /** @var array<int, mixed> */
    protected array $messages = [];

    /** @var array<int, Tool|string> */
    protected array $additionalTools = [];

    /** @var array<int, ProviderTool> */
    protected array $additionalProviderTools = [];

    protected ?Schema $schema = null;

    // Provider/model override
    protected Provider|string|null $providerOverride = null;

    protected ?string $modelOverride = null;

    // Config overrides
    protected ?int $maxTokensOverride = null;

    protected ?float $temperatureOverride = null;

    protected ?int $maxStepsOverride = null;

    protected ?bool $concurrentOverride = null;

    // Conversation support — stored here, transferred to agent on resolve
    protected ?Model $conversationOwner = null;

    protected ?Model $messageAuthor = null;

    protected ?int $conversationId = null;

    protected ?int $runtimeMessageLimit = null;

    protected bool $respondMode = false;

    protected bool $retryMode = false;

    public function __construct(
        protected readonly string $key,
        protected readonly AgentRegistry $agentRegistry,
        protected readonly ProviderRegistryContract $providerRegistry,
        protected readonly Application $app,
        protected readonly Dispatcher $events,
    ) {}

    // ─── Primary ────────────────────────────────────────────────────

    /**
     * Override the agent's system instructions.
     */
    public function instructions(string $directive): static
    {
        $this->instructionsOverride = $directive;

        return $this;
    }

    /**
     * Set the user message to send.
     *
     * @param  array<int, Input>|Input  $media
     */
    public function message(string $text, array|Input $media = []): static
    {
        $this->message = $text;
        $this->messageMedia = $media instanceof Input ? [$media] : $media;

        return $this;
    }

    // ─── Context ────────────────────────────────────────────────────

    /**
     * Provide conversation history messages.
     *
     * @param  array<int, mixed>  $messages
     */
    public function withMessages(array $messages): static
    {
        $this->messages = $this->normalizeMessages($messages);

        return $this;
    }

    // ─── Tools ──────────────────────────────────────────────────────

    /**
     * Add tools in addition to the agent's configured tools.
     *
     * @param  array<int, Tool|string>  $tools
     */
    public function withTools(array $tools): static
    {
        $this->additionalTools = $tools;

        return $this;
    }

    /**
     * Add provider tools in addition to the agent's configured provider tools.
     *
     * @param  array<int, ProviderTool>  $providerTools
     */
    public function withProviderTools(array $providerTools): static
    {
        $this->additionalProviderTools = $providerTools;

        return $this;
    }

    // ─── Config overrides ───────────────────────────────────────────

    /**
     * Override the agent's provider and model.
     */
    public function withProvider(Provider|string $provider, string $model): static
    {
        $this->providerOverride = $provider;
        $this->modelOverride = $model;

        return $this;
    }

    /**
     * Override the agent's max tokens.
     */
    public function withMaxTokens(int $tokens): static
    {
        $this->maxTokensOverride = $tokens;

        return $this;
    }

    /**
     * Override the agent's temperature.
     */
    public function withTemperature(float $temp): static
    {
        $this->temperatureOverride = $temp;

        return $this;
    }

    /**
     * Override the agent's max steps in the tool loop.
     */
    public function withMaxSteps(?int $maxSteps): static
    {
        $this->maxStepsOverride = $maxSteps;

        return $this;
    }

    /**
     * Override concurrent tool call execution for this call.
     */
    public function withConcurrent(bool $concurrent = true): static
    {
        $this->concurrentOverride = $concurrent;

        return $this;
    }

    /**
     * Set a structured output schema.
     */
    public function withSchema(Schema $schema): static
    {
        $this->schema = $schema;

        return $this;
    }

    // ─── Conversation Support ───────────────────────────────────────

    /**
     * Set the conversation owner (creates/finds conversation for this model).
     */
    public function for(Model $owner): static
    {
        $this->conversationOwner = $owner;

        return $this;
    }

    /**
     * Set the author of the incoming message.
     */
    public function asUser(Model $author): static
    {
        $this->messageAuthor = $author;

        return $this;
    }

    /**
     * Join an existing conversation by ID.
     */
    public function forConversation(int $conversationId): static
    {
        $this->conversationId = $conversationId;

        return $this;
    }

    /**
     * Respond to an existing conversation without a new user message.
     */
    public function respond(): static
    {
        $this->respondMode = true;

        return $this;
    }

    /**
     * Retry the last assistant response in the conversation.
     */
    public function retry(): static
    {
        $this->retryMode = true;

        return $this;
    }

    /**
     * Override the message history limit for this call.
     */
    public function withMessageLimit(int $limit): static
    {
        $this->runtimeMessageLimit = $limit;

        return $this;
    }

    // ─── Terminal ───────────────────────────────────────────────────

    /**
     * Execute the agent and return a text response.
     */
    public function asText(): TextResponse|PendingExecution
    {
        $traceId = (string) Str::uuid();

        if ($this->queued) {
            return $this->dispatchToQueue('asText');
        }

        $this->storeUserMessageEagerly();

        $agent = $this->resolveAgent();
        $tools = $this->resolveTools($agent);
        $driver = $this->resolveDriver($agent);
        $request = $this->buildRequest($agent, $tools);
        $provider = $this->resolveProviderKey();
        $model = $this->resolveModelKey();

        event(new ModalityStarted(modality: Modality::Text, provider: $provider, model: $model, agentKey: $agent->key(), traceId: $traceId));

        try {
            $result = $this->dispatchAgentMiddleware($agent, $request, $tools, function (AgentContext $ctx) use ($driver, $agent, $provider, $model, $traceId) {
                if ($ctx->tools === []) {
                    return $driver->text($ctx->request);
                }

                return $this->executeWithTools($driver, $ctx->request, $agent, $ctx->tools, $ctx->meta, $provider, $model, $traceId);
            });
        } catch (\Throwable $e) {
            event(new ModalityCompleted(modality: Modality::Text, provider: $provider, model: $model, agentKey: $agent->key(), traceId: $traceId));

            throw $e;
        }

        if ($result instanceof TextResponse) {
            event(new ModalityCompleted(modality: Modality::Text, provider: $provider, model: $model, usage: $result->usage, agentKey: $agent->key(), traceId: $traceId));

            return $result;
        }

        /** @var ExecutorResult $result */
        event(new ModalityCompleted(modality: Modality::Text, provider: $provider, model: $model, usage: $result->usage, agentKey: $agent->key(), traceId: $traceId));

        return $result->toTextResponse([
            'conversation_id' => $result->conversationId,
            'execution_id' => $result->executionId,
        ]);
    }

    /**
     * Execute the agent and return a stream response.
     */
    public function asStream(): StreamResponse|PendingExecution
    {
        $traceId = (string) Str::uuid();

        if ($this->queued) {
            return $this->dispatchToQueue('asStream');
        }

        $this->storeUserMessageEagerly();

        $agent = $this->resolveAgent();
        $tools = $this->resolveTools($agent);
        $driver = $this->resolveDriver($agent);
        $request = $this->buildRequest($agent, $tools);
        $provider = $this->resolveProviderKey();
        $model = $this->resolveModelKey();

        event(new ModalityStarted(modality: Modality::Stream, provider: $provider, model: $model, agentKey: $agent->key(), traceId: $traceId));

        try {
            $result = $this->dispatchAgentMiddleware($agent, $request, $tools, function (AgentContext $ctx) use ($driver, $agent, $provider, $model, $traceId) {
                if ($ctx->tools === []) {
                    return $driver->stream($ctx->request);
                }

                return $this->executeWithTools($driver, $ctx->request, $agent, $ctx->tools, $ctx->meta, $provider, $model, $traceId);
            });
        } catch (\Throwable $e) {
            event(new ModalityCompleted(modality: Modality::Stream, provider: $provider, model: $model, agentKey: $agent->key(), traceId: $traceId));

            throw $e;
        }

        $stream = $result instanceof StreamResponse
            ? $result
            : new StreamResponse($this->resultToChunks($result));

        // ModalityCompleted fires after the stream finishes, whether by success or error.
        // Using onFinally() ensures the event always pairs with ModalityStarted.
        $stream->onFinally(function () use ($stream, $provider, $model, $agent, $traceId) {
            event(new ModalityCompleted(modality: Modality::Stream, provider: $provider, model: $model, usage: $stream->getUsage(), agentKey: $agent->key(), traceId: $traceId));
        });

        // Pipe broadcast channel to the stream response
        if ($this->broadcastChannel !== null) {
            $stream->broadcastOn($this->broadcastChannel);
        }

        return $stream;
    }

    /**
     * Execute the agent and return a structured response.
     */
    public function asStructured(): StructuredResponse|PendingExecution
    {
        $traceId = (string) Str::uuid();

        if ($this->queued) {
            return $this->dispatchToQueue('asStructured');
        }

        $this->storeUserMessageEagerly();

        $agent = $this->resolveAgent();
        $driver = $this->resolveDriver($agent);
        $request = $this->buildRequest($agent, []);
        $provider = $this->resolveProviderKey();
        $model = $this->resolveModelKey();

        event(new ModalityStarted(modality: Modality::Structured, provider: $provider, model: $model, agentKey: $agent->key(), traceId: $traceId));

        try {
            $result = $this->dispatchAgentMiddleware($agent, $request, [], function (AgentContext $ctx) use ($driver) {
                return $driver->structured($ctx->request);
            });
        } catch (\Throwable $e) {
            event(new ModalityCompleted(modality: Modality::Structured, provider: $provider, model: $model, agentKey: $agent->key(), traceId: $traceId));

            throw $e;
        }

        event(new ModalityCompleted(modality: Modality::Structured, provider: $provider, model: $model, usage: $result->usage ?? null, agentKey: $agent->key(), traceId: $traceId));

        return $result;
    }

    /**
     * Start a voice session using the agent's config.
     *
     * Creates a browser-direct session with the provider, registers the
     * agent's tools for server-side execution, and returns a VoiceSession
     * with all endpoints the browser needs.
     */
    public function asVoice(): VoiceSession
    {
        $agent = $this->resolveAgent();
        $tools = $this->resolveTools($agent);

        // Inject agent identity variables
        $this->variables = array_merge([
            'NAME' => $agent->name(),
            'AGENT_KEY' => $agent->key(),
        ], $this->variables);

        // Resolve provider/model — voice agents should define their own provider() and model()
        $provider = $this->providerOverride
            ?? $agent->provider()
            ?? config('atlas.defaults.voice.provider');

        if ($provider === null) {
            throw AtlasException::missingDefault('voice');
        }

        $model = $this->modelOverride
            ?? $agent->model()
            ?? config('atlas.defaults.voice.model');

        $builder = Atlas::voice($provider, $model);

        // Build instructions with conversation history appended
        $instructions = $this->instructionsOverride ?? $agent->instructions();
        $usesConversations = in_array(HasConversations::class, class_uses_recursive($agent), true);

        if ($usesConversations && $this->conversationId !== null) {
            /** @var array<int, Message> $history */
            $history = $agent->conversationMessages(); // @phpstan-ignore method.notFound
            $voiceHistory = $agent->appendInstructionsForVoice($history);

            if ($voiceHistory !== '') {
                $instructions = $instructions !== null
                    ? $instructions."\n\n".$voiceHistory
                    : $voiceHistory;
            }
        }

        if ($instructions !== null) {
            $builder->instructions($this->interpolate($instructions));
        }

        if ($agent->voice() !== null) {
            $builder->withVoice($agent->voice());
        }

        if ($agent->temperature() !== null) {
            $builder->withTemperature($agent->temperature());
        }

        $builder->withInputTranscription();

        // Register tools in the session
        $toolMap = [];

        if ($tools !== []) {
            // Voice sessions always target OpenAI Realtime API which uses
            // the flat function format — direct instantiation is intentional.
            $toolMapper = new \Atlasphp\Atlas\Providers\OpenAi\ToolMapper;
            $toolDefs = $toolMapper->mapTools(
                array_map(fn (Tool $tool) => $tool->toDefinition(), $tools),
            );

            foreach ($tools as $tool) {
                $toolMap[$tool->name()] = $tool::class;
            }

            $builder->withTools($toolDefs);
        }

        // Create the session (gets ephemeral token from provider)
        $session = $builder->createSession();

        // Create execution record for voice session tracking
        $executionId = null;

        if (config('atlas.persistence.enabled')) {
            try {
                $providerKey = $provider instanceof Provider ? $provider->value : (string) $provider;

                /** @var class-string<Execution> $executionModel */
                $executionModel = config('atlas.persistence.models.execution', Execution::class);

                $execution = $executionModel::create([
                    'conversation_id' => $this->conversationId,
                    'agent' => $agent->key(),
                    'type' => ExecutionType::Voice,
                    'provider' => $providerKey,
                    'model' => $model ?? '',
                    'status' => ExecutionStatus::Processing,
                    'total_input_tokens' => 0,
                    'total_output_tokens' => 0,
                    'started_at' => now(),
                ]);

                $executionId = $execution->id;

                // Create VoiceCall record for transcript storage
                /** @var class-string<VoiceCall> $voiceCallModel */
                $voiceCallModel = config('atlas.persistence.models.voice_call', VoiceCall::class);

                $voiceCall = $voiceCallModel::create([
                    'conversation_id' => $this->conversationId,
                    'voice_session_id' => $session->sessionId,
                    'agent' => $agent->key(),
                    'provider' => $providerKey,
                    'model' => $model ?? '',
                    'status' => VoiceCallStatus::Active,
                    'transcript' => [],
                    'started_at' => now(),
                ]);

                // Link execution to voice call (Execution owns the relationship)
                $execution->update(['voice_call_id' => $voiceCall->id]);

                event(new VoiceCallStarted(
                    voiceCallId: $voiceCall->id,
                    conversationId: $this->conversationId,
                    sessionId: $session->sessionId,
                    provider: $providerKey,
                    agent: $agent->key(),
                ));
            } catch (\Throwable $e) {
                // Don't let persistence failures destroy a live voice session
                report($e);
            }
        }

        // Store tool class map + execution IDs in cache for the tool execution endpoint
        if ($toolMap !== [] || config('atlas.persistence.enabled')) {
            Cache::put("voice:{$session->sessionId}:tools", [
                'tools' => $toolMap,
                'user_id' => $this->messageAuthor?->getKey(),
                'execution_id' => $executionId,
            ], (int) config('atlas.persistence.voice_session_ttl', 60) * 60);
        }

        // Build endpoint URLs — always available (controllers gracefully skip when persistence is off)
        $prefix = config('atlas.persistence.voice_transcripts.route_prefix', 'atlas');
        $toolEndpoint = $toolMap !== [] ? url("/{$prefix}/voice/{$session->sessionId}/tool") : null;
        $transcriptEndpoint = url("/{$prefix}/voice/{$session->sessionId}/transcript");
        $closeEndpoint = url("/{$prefix}/voice/{$session->sessionId}/close");

        return $session->withEndpoints($toolEndpoint, $transcriptEndpoint, $closeEndpoint);
    }

    // ─── Queue: Eager User Message Storage ─────────────────────────

    /**
     * Override dispatchToQueue to store the user message BEFORE the job is dispatched.
     *
     * The user message is the consumer's action — it should persist immediately.
     * The job only handles the assistant's response. Without this, the user message
     * isn't stored until the job runs (which may be delayed).
     *
     * @param  array<string, mixed>  $terminalArgs
     */
    protected function dispatchToQueue(string $terminal, array $terminalArgs = []): PendingExecution
    {
        $this->storeUserMessageEagerly();

        return $this->traitDispatchToQueue($terminal, $terminalArgs);
    }

    /**
     * Store the user message to the conversation immediately (before job dispatch).
     *
     * After storing, switches to respond mode so PersistConversation middleware
     * in the job doesn't create a duplicate user message.
     */
    private function storeUserMessageEagerly(): void
    {
        if (! config('atlas.persistence.enabled')) {
            return;
        }

        // Only store if we have a message and a conversation
        if ($this->message === null || $this->conversationId === null) {
            return;
        }

        // Skip if already in respond or retry mode (no user message to store)
        if ($this->respondMode || $this->retryMode) {
            return;
        }

        $agent = $this->resolveAgent();

        if (! in_array(HasConversations::class, class_uses_recursive($agent), true)) {
            return;
        }

        try {
            $conversations = app(ConversationService::class);
            $conversation = $conversations->find($this->conversationId);

            $userMessage = new UserMessage(
                content: $this->message,
                media: $this->messageMedia,
            );

            $stored = $conversations->addMessage(
                $conversation,
                $userMessage,
                author: $this->messageAuthor,
            );

            $stored->markAsRead();

            // Store media attachments
            if ($this->messageMedia !== []) {
                $this->storeEagerMediaAttachments($stored->id, $this->messageMedia, $this->messageAuthor);
            }

            // Auto-set conversation title from first user message
            if ($conversation->title === null || $conversation->title === '') {
                $title = str_replace("\n", ' ', $this->message);
                $conversation->update([
                    'title' => mb_strlen($title) > 60 ? mb_substr($title, 0, 57).'...' : $title,
                ]);
            }

            event(new ConversationMessageStored(
                conversationId: $conversation->id,
                messageId: $stored->id,
                role: Role::User,
                agent: $agent->key(),
            ));

            // Switch to respond mode so the job doesn't store the user message again
            $this->respondMode = true;
        } catch (\Throwable $e) {
            // User message storage failed — the message will NOT be stored.
            // Execution continues so the consumer's call doesn't break.
            report($e);
        }
    }

    /**
     * Store user media attachments eagerly (mirrors PersistConversation logic).
     *
     * @param  array<int, Input>  $media
     */
    private function storeEagerMediaAttachments(int $messageId, array $media, ?Model $author): void
    {
        foreach ($media as $input) {
            try {
                $path = $input->store();
                $contents = $input->contents();
                $disk = config('atlas.storage.disk') ?? config('filesystems.default', 'local');
                $mime = $input->mimeType();

                $assetModel = config('atlas.persistence.models.asset', Asset::class);
                $attachmentModel = config('atlas.persistence.models.message_attachment', MessageAttachment::class);

                $type = match (true) {
                    str_starts_with($mime, 'image/') => AssetType::Image,
                    str_starts_with($mime, 'audio/') => AssetType::Audio,
                    str_starts_with($mime, 'video/') => AssetType::Video,
                    default => AssetType::Document,
                };

                DB::transaction(function () use ($assetModel, $attachmentModel, $messageId, $path, $disk, $mime, $contents, $author, $type): void {
                    $asset = $assetModel::create([
                        'type' => $type,
                        'mime_type' => $mime,
                        'filename' => basename($path),
                        'path' => $path,
                        'disk' => $disk,
                        'size_bytes' => strlen($contents),
                        'content_hash' => hash('sha256', $contents),
                        'author_type' => $author?->getMorphClass(),
                        'author_id' => $author?->getKey(),
                        'metadata' => ['source' => 'user_upload'],
                    ]);

                    $attachmentModel::create([
                        'message_id' => $messageId,
                        'asset_id' => $asset->id,
                    ]);
                });
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    // ─── Internal: Resolution ───────────────────────────────────────

    /**
     * Resolve the agent instance and transfer conversation state.
     */
    protected function resolveAgent(): Agent
    {
        $agent = $this->agentRegistry->resolve($this->key);

        $this->transferConversationState($agent);

        return $agent;
    }

    /**
     * Transfer conversation state from the request to the agent.
     */
    protected function transferConversationState(Agent $agent): void
    {
        $usesConversations = in_array(HasConversations::class, class_uses_recursive($agent), true);

        if (! $usesConversations) {
            return;
        }

        if ($this->conversationOwner !== null) {
            $agent->for($this->conversationOwner); // @phpstan-ignore method.notFound
        }

        if ($this->messageAuthor !== null) {
            $agent->asUser($this->messageAuthor); // @phpstan-ignore method.notFound
        }

        if ($this->conversationId !== null) {
            $agent->forConversation($this->conversationId); // @phpstan-ignore method.notFound
        }

        if ($this->runtimeMessageLimit !== null) {
            $agent->withMessageLimit($this->runtimeMessageLimit); // @phpstan-ignore method.notFound
        }

        if ($this->respondMode) {
            $agent->respond(); // @phpstan-ignore method.notFound
        }

        if ($this->retryMode) {
            $agent->retry(); // @phpstan-ignore method.notFound
        }
    }

    /**
     * Resolve the driver from provider override, agent config, or defaults.
     */
    protected function resolveDriver(Agent $agent): Driver
    {
        $provider = $this->providerOverride
            ?? $agent->provider()
            ?? config('atlas.defaults.text.provider');

        if ($provider === null) {
            throw AtlasException::missingDefault('agent');
        }

        $key = Provider::normalize($provider);

        return $this->providerRegistry->resolve($key);
    }

    /**
     * Resolve all tools (agent + runtime additions) into Tool instances.
     *
     * @return array<int, Tool>
     */
    protected function resolveTools(Agent $agent): array
    {
        $raw = array_merge($agent->tools(), $this->additionalTools);
        $tools = [];

        foreach ($raw as $item) {
            if ($item instanceof Tool) {
                $tools[] = $item;
            } elseif (is_string($item) && class_exists($item)) {
                $tools[] = $this->app->make($item);
            } elseif (is_string($item)) {
                throw new AtlasException("Tool class [{$item}] does not exist.");
            }
        }

        return $tools;
    }

    /**
     * Resolve provider tools (agent + runtime additions).
     *
     * @return array<int, ProviderTool>
     */
    protected function resolveProviderTools(Agent $agent): array
    {
        return array_merge($agent->providerTools(), $this->additionalProviderTools);
    }

    // ─── Internal: Build Request ────────────────────────────────────

    /**
     * Build the immutable TextRequest from agent config and runtime overrides.
     *
     * @param  array<int, Tool>  $tools
     */
    protected function buildRequest(Agent $agent, array $tools): TextRequest
    {
        // Inject agent identity variables
        $this->variables = array_merge([
            'NAME' => $agent->name(),
            'AGENT_KEY' => $agent->key(),
        ], $this->variables);

        // Resolve instructions with variable interpolation
        $rawInstructions = $this->instructionsOverride ?? $agent->instructions();
        $instructions = $this->interpolate($rawInstructions);

        // Resolve model
        $model = $this->modelOverride
            ?? $agent->model()
            ?? config('atlas.defaults.text.model');

        if ($model === null || $model === '') {
            throw AtlasException::missingDefault('agent model');
        }

        // Build tool definitions
        $toolDefinitions = array_map(
            fn (Tool $tool) => $tool->toDefinition(),
            $tools,
        );

        // Resolve provider tools
        $providerTools = $this->resolveProviderTools($agent);

        return new TextRequest(
            model: $model,
            instructions: $instructions,
            message: $this->message,
            messageMedia: $this->messageMedia,
            messages: $this->messages,
            maxTokens: $this->maxTokensOverride ?? $agent->maxTokens(),
            temperature: $this->temperatureOverride ?? $agent->temperature(),
            schema: $this->schema,
            tools: $toolDefinitions,
            providerTools: $providerTools,
            providerOptions: $this->providerOptions !== []
                ? $this->providerOptions
                : $agent->providerOptions(),
            middleware: $this->middleware,
            meta: $this->meta,
        );
    }

    // ─── Internal: Execution ────────────────────────────────────────

    /**
     * Execute the agent through the AgentExecutor tool loop.
     *
     * @param  array<int, Tool>  $tools
     * @param  array<string, mixed>  $meta
     */
    protected function executeWithTools(
        Driver $driver,
        TextRequest $request,
        Agent $agent,
        array $tools,
        array $meta = [],
        ?string $provider = null,
        ?string $model = null,
        ?string $traceId = null,
    ): ExecutorResult {
        // Rebuild tool definitions from the actual tools array — middleware
        // may have added tools after the request was built.
        $request = $request->withReplacedTools(array_map(
            fn (Tool $tool) => $tool->toDefinition(),
            $tools,
        ));

        $agentExecutor = AgentExecutor::forTools(
            driver: $driver,
            tools: $tools,
            events: $this->events,
            middlewareStack: $this->app->make(MiddlewareStack::class),
        );

        $maxSteps = $this->maxStepsOverride ?? $agent->maxSteps();

        $concurrent = $this->concurrentOverride ?? $agent->concurrent();

        return $agentExecutor->execute(
            request: $request,
            maxSteps: $maxSteps,
            concurrent: $concurrent,
            meta: $meta,
            context: new ExecutionContext(
                agentKey: $agent->key(),
                provider: $provider,
                model: $model,
                traceId: $traceId,
                broadcastChannel: $this->broadcastChannel,
            ),
        );
    }

    /**
     * Dispatch execution through agent middleware.
     *
     * @param  array<int, Tool>  $tools
     */
    protected function dispatchAgentMiddleware(
        Agent $agent,
        TextRequest $request,
        array $tools,
        \Closure $destination,
    ): mixed {
        $context = new AgentContext(
            request: $request,
            agent: $agent,
            messages: $this->messages,
            tools: $tools,
            meta: $this->meta,
        );

        $middleware = config('atlas.middleware.agent', []);

        if ($middleware === []) {
            return $destination($context);
        }

        /** @var MiddlewareStack $stack */
        $stack = $this->app->make(MiddlewareStack::class);

        return $stack->run(
            $context,
            $middleware,
            fn (AgentContext $ctx) => $destination($ctx),
        );
    }

    // ─── Queue Support ─────────────────────────────────────────────

    /**
     * Serialize all properties needed to rebuild this request in a queue worker.
     *
     * @return array<string, mixed>
     */
    public function toQueuePayload(): array
    {
        return [
            'key' => $this->key,
            'message' => $this->message,
            'instructions' => $this->instructionsOverride,
            'variables' => $this->variables,
            'meta' => $this->meta,
            'provider' => $this->providerOverride !== null
                ? Provider::normalize($this->providerOverride)
                : null,
            'model' => $this->modelOverride,
            'max_tokens' => $this->maxTokensOverride,
            'temperature' => $this->temperatureOverride,
            'max_steps' => $this->maxStepsOverride,
            'concurrent' => $this->concurrentOverride,
            'provider_options' => $this->providerOptions,
            'conversation_id' => $this->conversationId,
            'owner_type' => $this->conversationOwner?->getMorphClass(),
            'owner_id' => $this->conversationOwner?->getKey(),
            'author_type' => $this->messageAuthor?->getMorphClass(),
            'author_id' => $this->messageAuthor?->getKey(),
            'message_limit' => $this->runtimeMessageLimit,
            'respond_mode' => $this->respondMode,
            'retry_mode' => $this->retryMode,
            'message_media' => array_map(fn (Input $input) => [
                'class' => $input::class,
                'mime' => $input->mimeType(),
                'base64' => $input->isBase64() ? $input->data() : null,
                'url' => $input->isUrl() ? $input->url() : null,
                'storage_path' => $input->isStorage() ? $input->storagePath() : null,
                'storage_disk' => $input->isStorage() ? $input->storageDisk() : null,
                'path' => $input->isPath() ? $input->path() : null,
                'file_id' => $input->isFileId() ? $input->fileId() : null,
            ], $this->messageMedia),
            'middleware' => array_map(fn (mixed $m): string => is_string($m) ? $m : $m::class, $this->middleware),
        ];
    }

    /**
     * Rebuild this request from a queue payload and execute the given terminal method.
     *
     * @param  array<string, mixed>  $payload
     * @param  string  $terminal  Terminal method name (e.g. 'asText', 'asStream')
     * @param  int|null  $executionId  Pre-created execution ID for persistence
     * @param  Channel|null  $broadcastChannel  Channel for broadcasting
     */
    public static function executeFromPayload(
        array $payload,
        string $terminal,
        ?int $executionId = null,
        ?Channel $broadcastChannel = null,
    ): mixed {
        $request = Atlas::agent($payload['key']);

        if ($payload['message'] !== null) {
            $media = self::restoreMedia($payload['message_media'] ?? []);
            $request->message($payload['message'], $media);
        }

        if ($payload['instructions'] !== null) {
            $request->instructions($payload['instructions']);
        }

        if (! empty($payload['variables'])) {
            $request->withVariables($payload['variables']);
        }

        $meta = $payload['meta'] ?? [];

        if ($executionId !== null) {
            $meta['execution_id'] = $executionId;
        }

        if (! empty($meta)) {
            $request->withMeta($meta);
        }

        if ($payload['provider'] !== null && $payload['model'] !== null) {
            $request->withProvider($payload['provider'], $payload['model']);
        }

        if ($payload['max_tokens'] !== null) {
            $request->withMaxTokens($payload['max_tokens']);
        }

        if ($payload['temperature'] !== null) {
            $request->withTemperature($payload['temperature']);
        }

        if ($payload['max_steps'] !== null) {
            $request->withMaxSteps($payload['max_steps']);
        }

        if ($payload['concurrent'] !== null) {
            $request->withConcurrent($payload['concurrent']);
        }

        if (! empty($payload['provider_options'])) {
            $request->withProviderOptions($payload['provider_options']);
        }

        if (! empty($payload['middleware'])) {
            $request->withMiddleware($payload['middleware']);
        }

        // Restore conversation state
        if ($payload['owner_type'] !== null && $payload['owner_id'] !== null) {
            $owner = $payload['owner_type']::findOrFail($payload['owner_id']);
            $request->for($owner);
        }

        if ($payload['author_type'] !== null && $payload['author_id'] !== null) {
            $author = $payload['author_type']::findOrFail($payload['author_id']);
            $request->asUser($author);
        }

        if ($payload['conversation_id'] !== null) {
            $request->forConversation($payload['conversation_id']);
        }

        if ($payload['message_limit'] !== null) {
            $request->withMessageLimit($payload['message_limit']);
        }

        if ($payload['respond_mode'] ?? false) {
            $request->respond();
        }

        if ($payload['retry_mode'] ?? false) {
            $request->retry();
        }

        if ($broadcastChannel !== null) {
            $request->broadcastOn($broadcastChannel);
        }

        return match ($terminal) {
            'asText' => $request->asText(),
            'asStream' => $request->asStream(),
            'asStructured' => $request->asStructured(),
            default => throw new \InvalidArgumentException("Unknown terminal method: {$terminal}"),
        };
    }

    /**
     * Rebuild Input objects from serialized media array.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, Input>
     */
    protected static function restoreMedia(array $items): array
    {
        $media = [];

        foreach ($items as $item) {
            $input = self::restoreMediaItem($item);

            if ($input !== null) {
                $media[] = $input;
            }
        }

        return $media;
    }

    /**
     * Restore a single media input from its serialized form.
     *
     * @param  array<string, mixed>  $item
     */
    protected static function restoreMediaItem(array $item): ?Input
    {
        /** @var class-string<Input> $class */
        $class = $item['class'];

        if (! is_subclass_of($class, Input::class)) {
            return null;
        }

        if ($item['base64'] !== null && method_exists($class, 'fromBase64')) {
            return $class::fromBase64($item['base64'], $item['mime']);
        }

        if ($item['storage_path'] !== null && method_exists($class, 'fromStorage')) {
            return $class::fromStorage($item['storage_path'], $item['storage_disk']);
        }

        if ($item['url'] !== null && method_exists($class, 'fromUrl')) {
            return $class::fromUrl($item['url']);
        }

        if ($item['path'] !== null && method_exists($class, 'fromPath')) {
            return $class::fromPath($item['path']);
        }

        if ($item['file_id'] !== null && method_exists($class, 'fromFileId')) {
            return $class::fromFileId($item['file_id']);
        }

        return null;
    }

    /**
     * Resolve the provider as a string key for queue serialization.
     */
    protected function resolveProviderKey(): string
    {
        if ($this->providerOverride !== null) {
            return Provider::normalize($this->providerOverride);
        }

        $agent = $this->agentRegistry->resolve($this->key);
        $provider = $agent->provider();

        if ($provider !== null) {
            return Provider::normalize($provider);
        }

        return (string) config('atlas.defaults.text.provider', 'openai');
    }

    /**
     * Resolve the model as a string key for queue serialization.
     */
    protected function resolveModelKey(): string
    {
        if ($this->modelOverride !== null) {
            return $this->modelOverride;
        }

        $agent = $this->agentRegistry->resolve($this->key);

        return $agent->model() ?? (string) config('atlas.defaults.text.model', '');
    }
}
