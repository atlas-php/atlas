# Realtime Integration

This guide walks you through building a voice chat UI that connects to Atlas's realtime API, captures transcripts, and persists them to your conversation history.

For API reference and server-side configuration, see [Realtime Modality](/modalities/realtime).

## Overview

In WebRTC mode, audio flows directly between the browser and the AI provider. Your server handles two things:

1. **Session creation** — get an ephemeral token from the provider
2. **Transcript persistence** — receive transcript turns from the browser and store them as messages

```
Browser                          Your Server              Provider
  │                                  │                       │
  │  POST /your-endpoint  ──────►   │                       │
  │  (create session)                │  Atlas::realtime()    │
  │                                  │──────────────────────►│
  │  ◄── { token, endpoint }  ──    │                       │
  │                                  │                       │
  │  WebRTC audio ──────────────────────────────────────────►│
  │  ◄──────────────────────────────────────────── audio ───│
  │                                  │                       │
  │  Data channel transcripts  ◄─────────────────── events ─│
  │                                  │                       │
  │  POST /atlas/realtime/{id}/transcript ──►               │
  │  (save turns)                    │  (stores messages)    │
```

## Step 1: Server — Session Endpoint

Create a controller that builds the realtime session and returns the client payload:

```php
use Atlasphp\Atlas\Facades\Atlas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoiceChatController
{
    public function createSession(Request $request): JsonResponse
    {
        $user = $request->user();

        $session = Atlas::realtime()
            ->instructions('You are a helpful voice assistant.')
            ->withVoice('marin')
            ->withInputTranscription() // required for user speech transcripts
            ->createSession();

        // Build the transcript endpoint URL
        $transcriptEndpoint = null;
        if (config('atlas.persistence.enabled') && config('atlas.persistence.realtime_transcripts.enabled', true)) {
            $prefix = config('atlas.persistence.realtime_transcripts.route_prefix', 'atlas');
            $transcriptEndpoint = url("/{$prefix}/realtime/{$session->sessionId}/transcript");
        }

        $payload = $session->toClientPayload($transcriptEndpoint);

        // Include author info so transcripts are linked to the user
        $payload['author_type'] = $user->getMorphClass();
        $payload['author_id'] = $user->getKey();
        $payload['agent'] = 'assistant'; // your agent key

        return response()->json($payload);
    }
}
```

Register the route:

```php
Route::post('/voice/session', [VoiceChatController::class, 'createSession'])
    ->middleware('auth');
```

::: tip withInputTranscription() is required
Without this, OpenAI will not send `conversation.item.input_audio_transcription.completed` events, and user speech will not be transcribed.
:::

## Step 2: Configuration

In `config/atlas.php`, enable persistence and realtime transcripts:

```php
'persistence' => [
    'enabled' => true,

    'realtime_transcripts' => [
        'enabled' => true,
        'middleware' => ['web', 'auth'], // protect with your auth
        'route_prefix' => 'atlas',      // POST /atlas/realtime/{id}/transcript
    ],
],
```

Atlas automatically registers the transcript endpoint when both `persistence.enabled` and `realtime_transcripts.enabled` are `true`.

## Step 3: Frontend — Session Lifecycle

### Create the Session

```javascript
async function startVoiceSession(conversationId) {
    // 1. Create session via your server
    const res = await fetch('/voice/session', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ conversation_id: conversationId }),
    });

    const session = await res.json();
    // session contains: session_id, ephemeral_token, model,
    //   transcript_endpoint, author_type, author_id, agent
    return session;
}
```

### Connect WebRTC

```javascript
async function connectWebRtc(session) {
    // Get microphone
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });

    // Create peer connection
    const pc = new RTCPeerConnection();
    stream.getTracks().forEach(track => pc.addTrack(track, stream));

    // Remote audio playback
    const audioEl = document.createElement('audio');
    audioEl.autoplay = true;
    pc.ontrack = (e) => { audioEl.srcObject = e.streams[0]; };

    // Data channel for transcript events
    const dc = pc.createDataChannel('oai-events');
    dc.onmessage = (event) => handleTranscriptEvent(JSON.parse(event.data));

    // SDP exchange
    const offer = await pc.createOffer();
    await pc.setLocalDescription(offer);

    const sdpRes = await fetch(
        `https://api.openai.com/v1/realtime?model=${session.model}`,
        {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${session.ephemeral_token}`,
                'Content-Type': 'application/sdp',
            },
            body: offer.sdp,
        }
    );

    await pc.setRemoteDescription({
        type: 'answer',
        sdp: await sdpRes.text(),
    });

    return { pc, dc, stream };
}
```

## Step 4: Frontend — Transcript Capture

The data channel receives events from OpenAI. The key events for transcripts:

| Event | Description |
|-------|-------------|
| `conversation.item.input_audio_transcription.completed` | User finished speaking — `data.transcript` has the text |
| `response.audio_transcript.delta` | Assistant speech chunk — `data.delta` has incremental text |
| `response.done` | Turn complete — time to save the turn pair |

### Accumulate and Flush Turns

```javascript
let pendingUserText = '';
let pendingAssistantText = '';

function handleTranscriptEvent(data) {
    switch (data.type) {
        case 'conversation.item.input_audio_transcription.completed':
            pendingUserText = data.transcript ?? '';
            break;

        case 'response.audio_transcript.delta':
            pendingAssistantText += data.delta ?? '';
            break;

        case 'response.done':
            // Delay slightly — the user transcription event
            // sometimes arrives after response.done
            setTimeout(() => flushTurn(), 300);
            break;
    }
}

async function flushTurn() {
    const turns = [];

    if (pendingUserText) {
        turns.push({ role: 'user', transcript: pendingUserText });
    }
    if (pendingAssistantText) {
        turns.push({ role: 'assistant', transcript: pendingAssistantText });
    }

    // Reset for next turn
    pendingUserText = '';
    pendingAssistantText = '';

    if (turns.length === 0) return;

    // POST to Atlas's package endpoint
    await fetch(session.transcript_endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            conversation_id: conversationId,
            turns: turns,
            agent: session.agent,
            author_type: session.author_type,
            author_id: session.author_id,
        }),
    });
}
```

### Emergency Save on Tab Close

Use `sendBeacon` to save any unsaved transcript when the user closes the tab:

```javascript
window.addEventListener('beforeunload', () => {
    if (!pendingUserText && !pendingAssistantText) return;

    const turns = [];
    if (pendingUserText) turns.push({ role: 'user', transcript: pendingUserText });
    if (pendingAssistantText) turns.push({ role: 'assistant', transcript: pendingAssistantText });

    navigator.sendBeacon(
        session.transcript_endpoint,
        new Blob([JSON.stringify({
            conversation_id: conversationId,
            turns,
            agent: session.agent,
            author_type: session.author_type,
            author_id: session.author_id,
        })], { type: 'application/json' })
    );
});
```

## Step 5: Display Voice Messages

Voice messages are stored as regular conversation messages with metadata:

```json
{
    "source": "realtime",
    "session_id": "rt_abc123..."
}
```

When rendering messages, check `metadata.source` to show a voice indicator:

```html
<!-- Example: show a wave icon for voice messages -->
<div v-if="message.metadata?.source === 'realtime'" class="voice-badge">
    <AudioLinesIcon />
    Voice
</div>
```

Voice messages should **not** offer retry — they're transcripts of spoken conversation, not AI-generated responses that can be regenerated.

## Step 6: Load Conversation History After Session

When the voice session ends, refresh the conversation to show the persisted transcripts:

```javascript
function stopVoiceSession() {
    // Flush remaining turn
    flushTurn();

    // Clean up WebRTC
    pc.close();
    stream.getTracks().forEach(t => t.stop());

    // Reload messages after a short delay for the final POST
    setTimeout(() => loadConversation(conversationId), 500);
}
```

## Passing Conversation Context

If the user was in a text conversation before switching to voice, you may want the AI to know what was discussed. Inject the conversation history into the session instructions:

```php
public function createSession(Request $request): JsonResponse
{
    $conversationId = $request->integer('conversation_id');

    // Load recent messages as context
    $instructions = 'You are a helpful voice assistant.';

    if ($conversationId) {
        $conversation = Conversation::with(['messages' => fn ($q) =>
            $q->where('is_active', true)
                ->whereIn('role', [MessageRole::User, MessageRole::Assistant])
                ->whereNotNull('content')
                ->orderBy('sequence')
                ->limit(50)
        ])->find($conversationId);

        if ($conversation?->messages->isNotEmpty()) {
            $history = $conversation->messages
                ->map(fn ($m) => ($m->role === MessageRole::User ? 'User' : 'Assistant') . ": {$m->content}")
                ->implode("\n");

            $instructions .= "\n\nConversation so far:\n\n{$history}";
        }
    }

    $session = Atlas::realtime()
        ->instructions($instructions)
        ->withInputTranscription()
        ->createSession();

    // ... return payload
}
```

## Transcript Endpoint Reference

**Route:** `POST /{route_prefix}/realtime/{sessionId}/transcript`

Only registered when `atlas.persistence.enabled` and `atlas.persistence.realtime_transcripts.enabled` are both `true`.

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `conversation_id` | integer | Yes | Target conversation ID |
| `turns` | array | Yes | Array of turn objects |
| `turns.*.role` | string | Yes | `user` or `assistant` |
| `turns.*.transcript` | string | Yes | Transcript text (min 1 char) |
| `agent` | string | No | Agent key for assistant messages |
| `author_type` | string | No | Morph class or alias for user author |
| `author_id` | integer | No | Author model ID |

### Response

```json
{
    "stored": [101, 102]
}
```

Returns an array of created message IDs.

### Message Storage Details

- Messages are stored via `ConversationService::addMessage()`
- Tagged with `metadata: { "source": "realtime", "session_id": "..." }`
- Automatically marked as `read_at = now()` (voice messages are always "read")
- Assistant messages link to the preceding user message via `parent_id`
- `ConversationMessageStored` event fires for each stored message

### Middleware

The route is protected by the middleware configured in `atlas.persistence.realtime_transcripts.middleware`. The default is `['web', 'auth']`. Adjust this to match your application's auth setup:

```php
// Token-based auth (Sanctum)
'middleware' => ['auth:sanctum'],

// API guard
'middleware' => ['auth:api'],

// No auth (development only)
'middleware' => [],
```
