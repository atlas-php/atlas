# Voice Integration

This guide walks you through building a voice chat UI that connects to Atlas's voice API, handles tool calls server-side, and persists transcripts.

For API reference, see [Voice Modality](/modalities/voice).

## Overview

The browser connects directly to the AI provider for audio. Your server handles two things:

1. **Session creation** — get an ephemeral token or signed URL from the provider
2. **Tool execution** — resolve and run Atlas Tool classes when the AI calls tools

Transcript persistence is optional and automatic when enabled.

## Step 1: Server — Session Endpoint

### Using an Agent (Recommended)

```php
use Atlasphp\Atlas\Facades\Atlas;

class VoiceController
{
    public function createSession(Request $request): JsonResponse
    {
        $user = $request->user();

        $session = Atlas::agent('support')
            ->for($user)
            ->asUser($user)
            ->forConversation($request->integer('conversation_id'))
            ->asVoice();

        return response()->json($session->toClientPayload());
    }
}
```

This automatically:
- Uses the agent's instructions, tools, voice, and temperature
- Registers tools for server-side execution
- Gets an ephemeral token from the provider
- Returns session config for the browser

### Standalone (No Agent)

```php
$session = Atlas::voice()
    ->instructions('You are a helpful assistant.')
    ->withVoice('alloy')
    ->withInputTranscription()
    ->createSession();

return response()->json($session->toClientPayload());
```

## Step 2: Configuration

```php
// config/atlas.php
'defaults' => [
    'voice' => [
        'provider' => env('ATLAS_VOICE_PROVIDER', 'openai'),
        'model' => env('ATLAS_VOICE_MODEL', 'gpt-4o-realtime-preview'),
    ],
],

'persistence' => [
    'voice_transcripts' => [
        'enabled' => true,
        'middleware' => ['auth:sanctum'],
        'route_prefix' => 'atlas',
    ],
],
```

## Step 3: Frontend — Connect to Provider

The `toClientPayload()` response includes everything the browser needs:

```json
{
    "session_id": "rt_xai_abc123...",
    "provider": "xai",
    "model": "grok-3-fast-realtime",
    "transport": "websocket",
    "ephemeral_token": "xai-realtime-client-secret-...",
    "connection_url": "wss://api.x.ai/v1/realtime",
    "session_config": { "voice": "eve", "modalities": ["text", "audio"] },
    "tool_endpoint": "/atlas/voice/rt_xai_abc123.../tool",
    "transcript_endpoint": "/atlas/voice/rt_xai_abc123.../transcript",
    "close_endpoint": "/atlas/voice/rt_xai_abc123.../close"
}
```

### xAI (WebSocket)

```javascript
const ws = new WebSocket(session.connection_url, [
    'realtime',
    `xai-client-secret.${session.ephemeral_token}`
]);

ws.onopen = () => {
    ws.send(JSON.stringify({
        type: 'session.update',
        session: session.session_config,
    }));
    // Start sending mic audio...
};

ws.onmessage = (event) => {
    const data = JSON.parse(event.data);
    // Handle audio, transcripts, tool calls...
};
```

### OpenAI (WebRTC)

```javascript
const pc = new RTCPeerConnection();
stream.getTracks().forEach(track => pc.addTrack(track, stream));

const dc = pc.createDataChannel('oai-events');
const offer = await pc.createOffer();
await pc.setLocalDescription(offer);

const res = await fetch(`https://api.openai.com/v1/realtime?model=${session.model}`, {
    method: 'POST',
    headers: {
        Authorization: `Bearer ${session.ephemeral_token}`,
        'Content-Type': 'application/sdp',
    },
    body: offer.sdp,
});

await pc.setRemoteDescription({ type: 'answer', sdp: await res.text() });
```

### ElevenLabs (WebSocket via Signed URL)

```javascript
const ws = new WebSocket(session.connection_url);

ws.onopen = () => {
    // Send config override as first message (per-session customization)
    if (session.session_config?.conversation_config_override) {
        ws.send(JSON.stringify({
            type: 'conversation_initiation_client_data',
            conversation_config_override: session.session_config.conversation_config_override,
        }));
    }
    // Start sending mic audio...
};

ws.onmessage = (event) => {
    const data = JSON.parse(event.data);

    switch (data.type) {
        case 'audio':
            playAudioChunk(data.audio_event.audio_base_64);
            break;
        case 'agent_response':
            updateTranscript(data.agent_response_event.agent_response);
            break;
        case 'client_tool_call':
            handleToolCall(data.client_tool_call, ws);
            break;
        case 'ping':
            ws.send(JSON.stringify({ type: 'pong', event_id: data.ping_event.event_id }));
            break;
    }
};
```

## Handling Interruptions

When the user speaks while the AI is speaking, the AI should stop (barge-in). Without interruption handling, both voices overlap.

**getUserMedia setup** — enable browser echo cancellation:

```javascript
const stream = await navigator.mediaDevices.getUserMedia({
    audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true }
});
```

**OpenAI / xAI** — listen for `input_audio_buffer.speech_started` and send `response.cancel`:

```javascript
case 'input_audio_buffer.speech_started':
    if (isSpeaking) {
        // Stop audio playback
        audioQueue = [];
        isSpeaking = false;

        // Tell provider to stop generating
        ws.send(JSON.stringify({ type: 'response.cancel' }));
    }
    break;
```

**ElevenLabs** — listen for `interruption` and stop playback:

```javascript
case 'interruption':
    audioQueue = [];
    isSpeaking = false;
    break;
```

**Keep mic audio flowing** — do NOT mute the mic during AI speech. The server needs continuous audio to detect interruptions via VAD. Browser echo cancellation handles the feedback loop.

## Step 4: Handle Tool Calls

When the AI calls a tool, the browser receives a provider-specific event. POST the tool name and arguments to the tool endpoint:

```javascript
async function handleToolCall(toolCall, ws) {
    const res = await fetch(session.tool_endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            name: toolCall.name || toolCall.tool_name,
            arguments: typeof toolCall.arguments === 'string'
                ? toolCall.arguments
                : JSON.stringify(toolCall.parameters || toolCall.arguments),
        }),
    });

    const { output } = await res.json();

    // Send result back — format varies by provider
    // OpenAI/xAI: conversation.item.create with function_call_output
    // ElevenLabs: client_tool_result with tool_call_id
}
```

The server resolves the Tool class from the agent's registered tools and calls `handle()`. No custom server code needed.

## Step 5: Checkpoint Transcripts

The browser POSTs completed turns to the transcript endpoint as a checkpoint on each `response.done`. The server saves them to the `VoiceCall` record atomically — no messages are created.

```javascript
// After each response.done, send the accumulated turns
await fetch(session.transcript_endpoint, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        turns: [
            { role: 'user', content: userText },
            { role: 'assistant', content: assistantText },
        ],
    }),
});
```

Each checkpoint replaces the entire transcript on the VoiceCall record. If the page reloads, only the in-progress turn is lost.

## Step 6: Close Session

When the WebSocket disconnects, POST to the close endpoint with the final transcript:

```javascript
ws.onclose = () => {
    if (session.close_endpoint) {
        const body = JSON.stringify({ turns: completedTurns });
        navigator.sendBeacon(session.close_endpoint, new Blob([body], { type: 'application/json' }));
    }
};
```

Using `sendBeacon` ensures the request fires even on page unload. The endpoint is idempotent.

The close endpoint fires `VoiceCallCompleted` with the full transcript. Consumers listen for this event to generate summaries, create conversation messages, or embed into memory.

## Audio Format

OpenAI and xAI use PCM16 at 24kHz. ElevenLabs uses PCM16 at 16kHz by default (configurable). The browser captures mic audio, encodes as base64 PCM16, and sends over the WebSocket. Response audio is decoded for Web Audio API playback.

## Tool Endpoint Reference

**Route:** `POST /atlas/voice/{sessionId}/tool`

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Tool function name |
| `arguments` | string | Yes | JSON-encoded arguments |
| `call_id` | string | No | Provider-assigned tool call ID (generated if not provided) |

**Response:**
```json
{ "output": "Tool result string" }
```

## Close Endpoint Reference

**Route:** `POST /atlas/voice/{sessionId}/close`

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `turns` | array | No | Final transcript turns (existing transcript used if not provided) |

**Response:** `204 No Content`

Fires events in order:
1. `VoiceCallCompleted` — with full transcript and duration (for post-processing)
2. `VoiceSessionClosed` — with provider and session ID (for cleanup)

Idempotent. Cleans up cached session data and marks the linked execution as completed.

## Transcript Endpoint Reference

**Route:** `POST /atlas/voice/{sessionId}/transcript`

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `turns` | array | Yes | Turn objects |
| `turns.*.role` | string | Yes | `user` or `assistant` |
| `turns.*.content` | string | Yes | Transcript text |

**Response:**
```json
{ "saved": true }
```

Atomically replaces the VoiceCall transcript. No messages are created.
