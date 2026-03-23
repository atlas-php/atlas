# Realtime

Realtime voice-to-voice enables bidirectional speech conversations with AI providers. Unlike one-shot HTTP audio requests, realtime sessions maintain a persistent connection for continuous, low-latency voice interaction.

## Supported Providers

| Provider | Models | Transport | Pricing |
|----------|--------|-----------|---------|
| OpenAI | `gpt-4o-realtime-preview`, `gpt-4o-mini-realtime-preview` | WebRTC, WebSocket | ~$0.30/min |
| xAI | `grok-3-fast-realtime` | WebSocket | ~$0.05/min |

### Voices

Both providers support the same set of voices:

`alloy`, `ash`, `ballad`, `coral`, `echo`, `sage`, `shimmer`, `verse`, `marin`, `cedar`

## Quick Start

Create a session and get an ephemeral token for WebRTC:

```php
use Atlasphp\Atlas\Facades\Atlas;

$session = Atlas::realtime('openai', 'gpt-4o-realtime-preview-2024-12-17')
    ->instructions('You are a helpful voice assistant.')
    ->withVoice('alloy')
    ->createSession();

// Send to browser for WebRTC connection
return response()->json($session->toClientPayload());
```

## Configuration

Add defaults to `config/atlas.php`:

```php
'defaults' => [
    'realtime' => [
        'provider' => env('ATLAS_REALTIME_PROVIDER', 'openai'),
        'model' => env('ATLAS_REALTIME_MODEL', 'gpt-4o-realtime-preview-2024-12-17'),
    ],
],
```

Then use without specifying provider/model:

```php
$session = Atlas::realtime()
    ->instructions('You are a helpful assistant.')
    ->createSession();
```

## Fluent API

| Method | Description |
|--------|-------------|
| `instructions(string)` | System instructions for the session |
| `withVoice(string)` | Voice ID — `alloy`, `ash`, `ballad`, `coral`, `echo`, `sage`, `shimmer`, `verse`, `marin`, `cedar` |
| `viaWebRtc()` | Use WebRTC transport (default) |
| `viaWebSocket()` | Use WebSocket proxy transport |
| `withServerVad(?threshold, ?silenceDuration)` | Enable server-side voice activity detection |
| `withManualTurnDetection()` | Push-to-talk mode |
| `withTools(array)` | Register tool definitions for the session |
| `withTemperature(float)` | Response temperature |
| `withMaxResponseTokens(int)` | Max tokens per response |
| `withInputFormat(string)` | Input audio format (`pcm16`, `g711_ulaw`, `g711_alaw`) |
| `withOutputFormat(string)` | Output audio format |
| `withInputTranscription(string)` | Enable input audio transcription (`whisper-1`, `gpt-4o-transcribe`, etc.) |
| `withProviderOptions(array)` | Provider-specific options |
| `withMiddleware(array)` | Provider middleware |
| `withMeta(array)` | Request metadata |
| `createSession()` | Execute and return `RealtimeSession` |

## WebRTC Mode

WebRTC provides the lowest latency by connecting the browser directly to the provider. Your server only handles session creation (getting an ephemeral token).

```php
// Server: create session with ephemeral token
$session = Atlas::realtime('openai', 'gpt-4o-realtime-preview-2024-12-17')
    ->instructions('You are a helpful assistant.')
    ->viaWebRtc()
    ->createSession();

// The session contains:
$session->ephemeralToken;  // Short-lived token for browser
$session->sessionId;       // Session identifier
$session->expiresAt;       // Token expiration
$session->toClientPayload(); // Safe subset for the browser
```

In the browser, use the ephemeral token to establish a WebRTC peer connection:

```javascript
// 1. Get user microphone
const stream = await navigator.mediaDevices.getUserMedia({ audio: true });

// 2. Create peer connection and add mic track
const pc = new RTCPeerConnection();
stream.getTracks().forEach(track => pc.addTrack(track, stream));

// 3. Create data channel for events
const dc = pc.createDataChannel('oai-events');

// 4. SDP exchange with provider
const offer = await pc.createOffer();
await pc.setLocalDescription(offer);

const response = await fetch(`https://api.openai.com/v1/realtime?model=${model}`, {
    method: 'POST',
    headers: {
        'Authorization': `Bearer ${ephemeralToken}`,
        'Content-Type': 'application/sdp',
    },
    body: offer.sdp,
});

await pc.setRemoteDescription({ type: 'answer', sdp: await response.text() });
```

## WebSocket Proxy Mode

WebSocket proxy routes all audio through your Laravel server, giving you full control over the data stream. Requires [Laravel Horizon](https://laravel.com/docs/horizon) for queue processing.

```php
$session = Atlas::realtime('openai', 'gpt-4o-realtime-preview-2024-12-17')
    ->instructions('You are a helpful assistant.')
    ->viaWebSocket()
    ->createSession();

// Connect server-side WebSocket
$driver = Atlas::providers()->resolve('openai');
$connection = $driver->connectRealtime($session);

// Send/receive events
$connection->send(new RealtimeEvent('input_audio_buffer.append', data: [
    'audio' => $base64AudioChunk,
]));

$event = $connection->receive();
```

## Turn Detection

### Server VAD (default)

The provider automatically detects when the user stops speaking:

```php
Atlas::realtime()
    ->withServerVad(
        threshold: 0.5,        // Sensitivity (0.0–1.0)
        silenceDuration: 500,  // Silence before turn end (ms)
    )
    ->createSession();
```

### Manual (Push-to-Talk)

The client explicitly signals turn boundaries:

```php
Atlas::realtime()
    ->withManualTurnDetection()
    ->createSession();
```

## Tool Calling

Tools work in realtime sessions just like in text completions. Define tools when creating the session:

```php
$session = Atlas::realtime()
    ->withTools([
        [
            'type' => 'function',
            'name' => 'get_weather',
            'description' => 'Get current weather',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'location' => ['type' => 'string'],
                ],
            ],
        ],
    ])
    ->createSession();
```

When the AI invokes a tool, a `RealtimeToolCallRequested` event is dispatched with the call ID, function name, and arguments.

## Audio Formats

| Format | Description | Default |
|--------|-------------|---------|
| `pcm16` | 16-bit PCM, little-endian | Yes (OpenAI: 24kHz, xAI: variable) |
| `g711_ulaw` | G.711 μ-law | No |
| `g711_alaw` | G.711 A-law | No |

```php
Atlas::realtime()
    ->withInputFormat('pcm16')
    ->withOutputFormat('pcm16')
    ->createSession();
```

## Transcript Persistence

Atlas can automatically persist voice transcripts as conversation messages. When enabled, a package-level HTTP endpoint accepts transcript turns from the browser and stores them in the same conversation thread as text messages. See the [Realtime Integration Guide](/guides/realtime-integration) for full frontend setup.

### Enable Persistence

In `config/atlas.php`, ensure persistence is enabled:

```php
'persistence' => [
    'enabled' => true,
    // ...
    'realtime_transcripts' => [
        'enabled' => true,
        'middleware' => ['web', 'auth'], // your auth middleware
        'route_prefix' => 'atlas',
    ],
],
```

### Enable Input Transcription

OpenAI only sends user speech transcripts when `input_audio_transcription` is configured:

```php
$session = Atlas::realtime()
    ->instructions('You are a helpful assistant.')
    ->withInputTranscription() // defaults to whisper-1
    ->createSession();
```

### Transcript Endpoint

When persistence is enabled, `$session->toClientPayload()` includes a `transcript_endpoint` URL. The browser sends completed turns to this endpoint:

```
POST /{route_prefix}/realtime/{sessionId}/transcript
```

**Request body:**
```json
{
    "conversation_id": 42,
    "turns": [
        { "role": "user", "transcript": "What's the weather like?" },
        { "role": "assistant", "transcript": "It's sunny and 72 degrees." }
    ],
    "agent": "assistant",
    "author_type": "App\\Models\\User",
    "author_id": 1
}
```

Stored messages are tagged with `metadata.source = 'realtime'` and `metadata.session_id`, and are automatically marked as read.

## Events

| Event | Description |
|-------|-------------|
| `RealtimeSessionCreated` | Session successfully created |
| `RealtimeSessionClosed` | Session ended |
| `RealtimeAudioDelta` | Audio chunk (broadcastable) |
| `RealtimeTranscriptDelta` | Transcript chunk (broadcastable) |
| `RealtimeToolCallRequested` | AI requested a tool call |
| `ModalityStarted` | Standard modality lifecycle (Realtime) |
| `ModalityCompleted` | Standard modality lifecycle (Realtime) |

## Testing

Use `RealtimeSessionFake` with `Atlas::fake()`:

```php
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Testing\RealtimeSessionFake;

Atlas::fake([
    RealtimeSessionFake::make()
        ->withSessionId('test-session')
        ->withProvider('openai')
        ->withModel('gpt-4o-realtime-preview'),
]);

$session = Atlas::realtime('openai', 'gpt-4o-realtime-preview')
    ->instructions('Hello')
    ->createSession();

$session->sessionId; // 'test-session'
```
