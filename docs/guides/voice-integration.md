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

## Step 5: Persist Transcripts

When persistence is enabled, POST completed turns to the transcript endpoint:

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

## Audio Format

OpenAI and xAI use PCM16 at 24kHz. ElevenLabs uses PCM16 at 16kHz by default (configurable). The browser captures mic audio, encodes as base64 PCM16, and sends over the WebSocket. Response audio is decoded for Web Audio API playback.

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
