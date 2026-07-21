---
id: VOICE
name: Voice
---

## What this is

A live, two-way spoken conversation with an AI provider that can still call the application's tools. The
browser streams audio straight to the provider, while the application handles session setup, tool
execution, and transcripts.

## Why it exists

- A user holds a natural spoken conversation with an assistant that can act on their behalf.
- Audio streams directly to the provider, keeping it off the application server.
- Every conversation leaves a stored transcript the application can act on afterwards.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-VOICE-1 | Creating a live spoken session yields a short-lived token the browser connects with. | `creates a session with ephemeral token and WebSocket URL` |
| ✅ | R-VOICE-2 | The session gives the browser a direct provider connection for its audio stream. | `creates a WebSocket session with connection URL` |
| ✅ | R-VOICE-3 | A model's tool call is executed on the server, returning its result to the browser. | `executes a registered tool and returns the result` |
| ✅ | R-VOICE-4 | The spoken conversation's transcript is saved for later retrieval. | `saves transcript turns to VoiceCall record` |
| ✅ | R-VOICE-5 | Turn-taking and voice-activity detection behaviour is configurable for a session. | `withServerVad sets threshold and silence duration` |
| ✅ | R-VOICE-6 | The voice session routes carry no protective middleware by default. | `registers voice routes with no middleware by default` |
| ✅ | R-VOICE-7 | Consumer-configured middleware secures the voice session routes when supplied. | `applies configured voice.route_middleware to the voice routes` |

## Open questions

- Whether the abandoned-session cleanup and its retention window warrant their own guaranteed row.
