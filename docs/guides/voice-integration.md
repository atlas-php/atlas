# Voice Integration

This guide walks you through building a voice chat UI that connects to Atlas's voice API, handles tool calls server-side, and persists transcripts.

For API reference, see [Voice Modality](/modalities/voice).

## Overview

The browser connects directly to the AI provider for audio. Your server handles three things:
1. **Session creation** — get an ephemeral token
2. **Tool execution** — resolve and run Atlas Tool classes
3. **Transcript persistence** — store voice turns as messages

```
Browser                      Your Server              Provider
  │                              │                       │
  │ POST /voice/session ────────►│                       │
  │                              │ ephemeral token ──────►│
  │ ◄── { token, endpoints }    │                       │
  │                              │                       │
  │ WebSocket/WebRTC direct ─────────────────────────────►│
  │ ◄───────────────────────────────────────── audio ───│
  │                              │                       │
  │ POST /atlas/voice/{id}/tool ►│ Tool::handle()        │
  │ ◄── { result } ─────────────│                       │
  │                              │                       │
  │ POST /atlas/voice/{id}/transcript ►│                 │
```

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
    ->withVoice('marin')
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
        'middleware' => ['web', 'auth'],
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
    "transcript_endpoint": "/atlas/voice/rt_xai_abc123.../transcript"
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

## Step 4: Handle Tool Calls

When the AI calls a tool, the browser receives a `response.function_call_arguments.done` event. POST it to the tool endpoint:

```javascript
async function handleToolCall(data, ws) {
    const res = await fetch(session.tool_endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            name: data.name,
            arguments: data.arguments,
        }),
    });

    const result = await res.json();

    ws.send(JSON.stringify({
        type: 'conversation.item.create',
        item: {
            type: 'function_call_output',
            call_id: data.call_id,
            output: result.output,
        },
    }));
    ws.send(JSON.stringify({ type: 'response.create' }));
}
```

The server resolves the Tool class from the agent's registered tools and calls `handle()`. No custom server code needed.

## Step 5: Persist Transcripts

On each `response.done` event, POST the turn transcripts:

```javascript
await fetch(session.transcript_endpoint, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        conversation_id: conversationId,
        turns: [
            { role: 'user', transcript: userText },
            { role: 'assistant', transcript: assistantText },
        ],
    }),
});
```

Messages are stored with `metadata.source = 'voice'` and appear in the conversation thread.

## Step 6: Display Voice Messages

Check `metadata.source` to show a voice indicator:

```html
<span v-if="message.metadata?.source === 'voice'">
    <AudioLinesIcon /> Voice
</span>
```

Voice messages should not offer retry — they're transcripts of spoken conversation.

## Audio Format

Both providers use PCM16 at 24kHz for input and output. The browser captures mic audio using `ScriptProcessorNode`, downsamples to 24kHz, and sends as base64-encoded PCM16. Response audio is decoded from PCM16 to float32 for Web Audio API playback.

## Echo Prevention

Mute the mic track while the AI is speaking to prevent the speaker audio from being captured and sent back, which would create a feedback loop:

```javascript
// On first audio chunk from AI
localStream.getAudioTracks().forEach(t => { t.enabled = false; });

// After response.done (with delay)
setTimeout(() => {
    localStream.getAudioTracks().forEach(t => { t.enabled = true; });
}, 500);
```

## Tool Endpoint Reference

**Route:** `POST /atlas/voice/{sessionId}/tool`

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Tool function name |
| `arguments` | string | Yes | JSON-encoded arguments |

**Response:**
```json
{ "output": "Tool result string" }
```

## Transcript Endpoint Reference

**Route:** `POST /atlas/voice/{sessionId}/transcript`

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `conversation_id` | integer | Yes | Target conversation |
| `turns` | array | Yes | Turn objects |
| `turns.*.role` | string | Yes | `user` or `assistant` |
| `turns.*.transcript` | string | Yes | Transcript text |
| `agent` | string | No | Agent key |
| `author_type` | string | No | Morph class or alias |
| `author_id` | integer | No | Author model ID |
