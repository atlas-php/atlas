# Voice

Voice enables bidirectional speech conversations with AI providers. The browser connects directly to the provider — audio never passes through your server. Your server handles session creation (ephemeral tokens), tool execution, and transcript persistence.

## Supported Providers

| Provider | Models | Transport | Pricing |
|----------|--------|-----------|---------|
| OpenAI | `gpt-4o-realtime-preview`, `gpt-4o-mini-realtime-preview` | WebRTC | ~$0.30/min |
| xAI | `grok-3-fast-realtime` | WebSocket | ~$0.05/min |
| ElevenLabs | Any LLM (GPT-4o, Claude, Gemini, etc.) | WebSocket | ~$0.08/min |

### Voices

**OpenAI:** `alloy`, `ash`, `ballad`, `coral`, `echo`, `sage`, `shimmer`, `verse`, `marin`, `cedar`

**xAI:** `ara`, `eve`, `leo`, `rex`, `sal`, `una`

**ElevenLabs:** 3,000+ voices via voice library — use any `voice_id` from `Atlas::provider('elevenlabs')->voices()`

## Quick Start

### With an Agent

The simplest way — uses the agent's tools, instructions, and persistence automatically:

```php
$session = Atlas::agent('support')
    ->for($user)
    ->asUser($user)
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

## Agent Voice Config

Agents can define voice-specific config:

```php
class SupportAgent extends Agent
{
    public function voiceProvider(): ?string
    {
        return 'xai'; // Different provider for voice
    }

    public function voiceModel(): ?string
    {
        return 'grok-3-fast-realtime';
    }

    public function voice(): ?string
    {
        return 'eve';
    }
}
```

When `asVoice()` is called, these override the agent's text provider/model.

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

## Transcript Persistence

When persistence is enabled, voice transcripts are stored as conversation messages:

```php
'persistence' => [
    'voice_transcripts' => [
        'enabled' => true,
        'middleware' => ['auth:sanctum'],
        'route_prefix' => 'atlas',
    ],
],
```

The browser POSTs completed turns to `/atlas/voice/{sessionId}/transcript` on each turn boundary. Messages are tagged with `metadata.source = 'voice'`.

## Events

| Event | Description |
|-------|-------------|
| `VoiceSessionCreated` | Session successfully created |
| `VoiceSessionClosed` | Session ended |
| `VoiceAudioDelta` | Audio chunk (broadcastable) |
| `VoiceTranscriptDelta` | Transcript chunk (broadcastable) |
| `VoiceToolCallRequested` | AI requested a tool call |
| `ModalityStarted` | Standard modality lifecycle |
| `ModalityCompleted` | Standard modality lifecycle |

## Testing

```php
use Atlasphp\Atlas\Facades\Atlas;
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
