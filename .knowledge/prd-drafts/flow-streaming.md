---
id: STREAM
name: Streaming
---

## What this is

The behaviour that delivers a reply piece by piece as it is produced, rather than all at once when finished. The pieces reach the caller live, can fan out to other listeners, and an interruption is never hidden.

## Why it exists

- A user sees a reply begin immediately instead of waiting for the whole answer.
- Many clients can watch the same reply arrive at once.
- A failure part-way through is surfaced, never mistaken for a finished reply.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-STREAM-1 | A reply arrives as a stream of typed chunks. | `accumulates text from chunks during iteration` |
| ✅ | R-STREAM-2 | The stream closes with a final chunk carrying the full text and token usage. | `sends done chunk as SSE done event with accumulated text and usage` |
| ✅ | R-STREAM-3 | A stream returned from a route is delivered to the client as live events. | `sends text chunks as SSE chunk events` |
| ✅ | R-STREAM-4 | Chunks can broadcast to a subscribed channel while the stream is delivered. | `broadcasts StreamChunkReceived for text chunks` |
| ✅ | R-STREAM-5 | Completion callbacks fire in registration order after the stream ends. | `multiple then callbacks fire in order` |
| ✅ | R-STREAM-6 | A provider failure mid-stream is raised, never silently truncated. | `fires finally callbacks when the stream errors and still rethrows` |
| ✅ | R-STREAM-7 | With tools, the tool loop completes first, then results and text stream. | `asStream with tools falls back to non-streaming and wraps as chunks` |

## Open questions

- None.
