# Voice

Voice enables bidirectional speech conversations with AI providers. The browser connects directly to the provider — audio never passes through your server. Your server handles session creation (ephemeral tokens), tool execution, and transcript persistence.

## Supported Providers

| Provider | Models | Transport | Pricing |
|----------|--------|-----------|---------|
| OpenAI | `gpt-4o-realtime-preview`, `gpt-4o-mini-realtime-preview` | WebRTC | ~$0.30/min |
| xAI | `grok-3-fast-realtime` | WebSocket | ~$0.05/min |
| ElevenLabs | Any LLM (GPT-4o, Claude, Gemini, etc.) | WebSocket | ~$0.08/min |

### Voices

**OpenAI:** `alloy`, `ash`, `ballad`, `cedar`, `coral`, `echo`, `fable`, `marin`, `nova`, `onyx`, `sage`, `shimmer`, `verse`

**xAI:** `ara`, `eve`, `leo`, `rex`, `sal`, `una`

**ElevenLabs:** 3,000+ voices via voice library — use any `voice_id` from `Atlas::provider('elevenlabs')->voices()`

## Quick Start

### With an Agent

The simplest way — uses the agent's tools, instructions, and persistence automatically:

```php
$session = Atlas::agent('support')
    ->for($user)
    ->forConversation($conversationId)
    ->asVoice();

return response()->json($session->toClientPayload());
```

### Standalone

For direct control without an agent:

```php
$session = Atlas::voice('openai', 'gpt-4o-realtime-preview')
    ->instructions('You are a helpful voice assistant.')
    ->withVoice('alloy')
    ->withInputTranscription()
    ->createSession();

return response()->json($session->toClientPayload());
```

## Configuration

Add defaults to `config/atlas.php`:

```php
'defaults' => [
    'voice' => [
        'provider' => env('ATLAS_VOICE_PROVIDER', 'openai'),
        'model' => env('ATLAS_VOICE_MODEL', 'gpt-4o-realtime-preview'),
    ],
],
```

## Fluent API

| Method | Description |
|--------|-------------|
| `instructions(string)` | System instructions for the session |
| `withVoice(string)` | Voice ID (e.g. `alloy`, `coral`, `shimmer` for OpenAI; `eve`, `ara` for xAI) |
| `viaWebRtc()` | Use WebRTC transport (OpenAI) |
| `viaWebSocket()` | Use WebSocket transport (xAI) |
| `withServerVad(?threshold, ?silenceDuration)` | Server-side voice activity detection |
| `withManualTurnDetection()` | Push-to-talk mode |
| `withTools(array)` | Register tool definitions |
| `withTemperature(float)` | Response temperature |
| `withMaxResponseTokens(int)` | Max tokens per response |
| `withInputFormat(string)` | Input audio format (`pcm16`, `g711_ulaw`, `g711_alaw`) |
| `withOutputFormat(string)` | Output audio format |
| `withInputTranscription(string)` | Enable input transcription (`whisper-1`, etc.) |
| `withProviderOptions(array)` | Provider-specific options |
| `createSession()` | Execute and return `VoiceSession` |

## Voice Agents

Create dedicated agents for voice mode with their own provider, model, and instructions:

```php
class VoiceSupportAgent extends Agent
{
    public function name(): string
    {
        return 'Sarah';
    }

    public function provider(): ?string
    {
        return 'xai';
    }

    public function model(): ?string
    {
        return 'grok-3-fast-realtime';
    }

    public function voice(): ?string
    {
        return 'eve'; // xAI voice ID
    }

    public function instructions(): ?string
    {
        return 'You are {NAME}, a friendly voice support agent. Keep responses concise and conversational.';
    }
}
```

The `voice()` method sets which voice ID the agent uses. When `asVoice()` is called, it applies the voice to the session. If not set, the provider's default voice is used. Use `{NAME}` in instructions to reference the agent's display name.

You can also set the voice at call time with `withVoice()` on the standalone builder:

```php
Atlas::voice('openai', 'gpt-4o-realtime-preview')
    ->withVoice('nova')
    ->createSession();
```

## Transport Modes

### WebRTC (OpenAI)

Browser connects directly via `RTCPeerConnection`. Audio flows peer-to-peer.

### WebSocket (xAI)

Browser connects directly via `WebSocket` using an ephemeral token from `POST /v1/realtime/client_secrets`. Audio is sent as base64-encoded PCM16 at 24kHz.

Both transports are browser-direct — your server never touches audio.

## Tool Calling

When using `agent()->asVoice()`, the agent's tools are registered in the session. Tool calls are handled via a package-provided endpoint:

1. Provider sends tool call to the browser
2. Browser POSTs `{ name, arguments }` to `/atlas/voice/{sessionId}/tool`
3. Server resolves and executes the Atlas Tool class
4. Browser sends the result back to the provider

No custom client-side tool code required — the voice composable handles the relay.

## Voice Calls & Transcripts

Voice transcripts are stored in a dedicated `atlas_voice_calls` table — not as individual messages. Each voice session creates one `VoiceCall` record with the complete transcript as a JSON array. Atlas stores the data; **you decide what to do with it**.

```php
use Atlasphp\Atlas\Persistence\Models\VoiceCall;

$call = VoiceCall::forSession($sessionId)->first();
$call->transcript;    // [{role: 'user', content: '...'}, {role: 'assistant', content: '...'}]
$call->summary;       // Your generated summary (nullable)
$call->duration_ms;   // Wall-clock duration
$call->conversation;  // Linked Conversation (nullable)
$call->executions;    // Linked Executions for tool call tracking
```

### Lifecycle

Voice calls follow a simple lifecycle: `active` → `completed` | `failed`

- **Active** — session is live, transcript is being checkpointed
- **Completed** — browser sent close request, or TTL cleanup ran
- **Failed** — explicitly marked by consumer code

The framework handles transitions automatically. You don't need to call `markCompleted()` yourself — the close endpoint does it.

### Querying Voice Calls

```php
// All calls for a conversation
VoiceCall::forConversation($conversationId)->get();

// By session ID
VoiceCall::forSession($sessionId)->first();

// Recent completed calls
VoiceCall::completed()->latest()->take(10)->get();

// Active calls (still in progress)
VoiceCall::active()->get();
```

### Transcript Checkpointing

The browser POSTs completed turns to `/atlas/voice/{sessionId}/transcript` on each `response.done`. The server replaces the VoiceCall transcript atomically — no duplicates, no progressive messages. If the page reloads, only the in-progress turn is lost.

### Session Completion

Voice calls are completed when:

1. **Close endpoint** — `POST /atlas/voice/{sessionId}/close` (browser calls on disconnect)
2. **Stale cleanup** — `atlas:clean-voice-sessions` sweeps abandoned sessions

The close endpoint fires `VoiceCallCompleted` with the full transcript.

### Stale Call Cleanup

Schedule the cleanup command for abandoned sessions:

```php
$schedule->command('atlas:clean-voice-sessions')->hourly();
```

Default TTL: 60 minutes. Configure via `ATLAS_VOICE_SESSION_TTL` env var or `atlas.persistence.voice_session_ttl`.

## Post-Processing Patterns

Atlas fires `VoiceCallCompleted` when a voice call ends. What you do with the transcript is entirely up to you. Here are common patterns:

### Pattern A: Summarize the Call

Generate a summary and store it on the VoiceCall record:

```php
use Atlasphp\Atlas\Events\VoiceCallCompleted;
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Persistence\Models\VoiceCall;

Event::listen(VoiceCallCompleted::class, function ($event) {
    if ($event->transcript === []) return;

    $voiceCall = VoiceCall::find($event->voiceCallId);

    $formatted = collect($event->transcript)
        ->map(fn ($turn) => ucfirst($turn['role']).': '.$turn['content'])
        ->implode("\n");

    try {
        $response = Atlas::text('xai', 'grok-3-mini-fast')
            ->instructions('Summarize this voice call in 2-3 sentences. Focus on key topics, decisions, and action items.')
            ->message($formatted)
            ->asText();

        $voiceCall->update(['summary' => $response->text]);
    } catch (\Throwable $e) {
        logger()->error('Voice call summarization failed', ['id' => $event->voiceCallId]);
    }
});
```

### Pattern B: Inject Summary into Conversation Thread

After summarizing, create a message in the conversation so the text agent sees it on the next turn:

```php
use Atlasphp\Atlas\Messages\SystemMessage;
use Atlasphp\Atlas\Persistence\Services\ConversationService;

Event::listen(VoiceCallCompleted::class, function ($event) {
    // ... generate summary as above ...

    // Inject into conversation thread
    if ($event->conversationId && $summary) {
        $conversations = app(ConversationService::class);
        $conversation = $conversations->find($event->conversationId);

        $conversations->addMessage($conversation, new SystemMessage(
            content: "[Voice call summary]\n{$summary}"
        ));
    }
});
```

Now when the user switches back to text, the agent sees the voice call summary in its conversation history.

### Pattern C: Embed into Agent Memory

Store key facts from the transcript in the agent's memory for long-term recall:

```php
Event::listen(VoiceCallCompleted::class, function ($event) {
    // Extract key facts using a cheap model, then store in memory
    // using your preferred memory storage approach
});
```

### Pattern D: Do Nothing

If you don't register a listener, the transcript is still stored in the VoiceCall record. You can query and process it later, display it in your UI, or ignore it entirely.

## Conversation Context Flow

When you use `forConversation($id)` with voice, the agent gets the conversation's text history as context in its instructions. This means the voice agent knows what was discussed in prior text messages.

```
Text messages in conversation
         ↓ (loaded as context)
Voice session starts → Agent has full history
         ↓
Voice call happens
         ↓
Transcript stored in VoiceCall (NOT as messages)
         ↓
VoiceCallCompleted fires
         ↓
Your listener runs → optional: create summary message
         ↓
Next text turn → Agent sees summary in conversation history
```

Voice transcripts are **not** automatically added to conversation messages. This is by design — you control whether the raw transcript, a summary, or nothing at all enters the conversation thread.

## Events

| Event | Description |
|-------|-------------|
| `VoiceCallStarted` | Voice call created and ready for connection |
| `VoiceCallCompleted` | Voice call completed with full transcript |
| `VoiceSessionClosed` | WebSocket connection closed |
| `VoiceAudioDeltaReceived` | Audio chunk (broadcastable) |
| `VoiceTranscriptDeltaReceived` | Transcript chunk (broadcastable) |
| `VoiceToolCallStarted` | Tool call received (fired by tool controller) |
| `ModalityStarted` | Standard modality lifecycle |
| `ModalityCompleted` | Standard modality lifecycle |

## Testing

```php
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Testing\VoiceSessionFake;

Atlas::fake([
    VoiceSessionFake::make()
        ->withSessionId('test-session')
        ->withProvider('openai'),
]);

$session = Atlas::voice('openai', 'gpt-4o-realtime-preview')
    ->instructions('Hello')
    ->createSession();

$session->sessionId; // 'test-session'
```
