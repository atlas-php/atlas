---
id: ASSET
name: Media assets
---

## What this is

Generated media — images, audio, and video — stored to a configured disk and tracked as an asset
linked to whatever produced it. Assets tie back to the call, the tool, and the message behind them,
and a media response can hand back its own contents. Storage happens automatically when persistence
is enabled and can be turned off.

## Why it exists

- Generated files land on disk without the developer wiring up storage.
- Every asset is traceable to the call, tool, and message that created it.
- A tool's output file shows up inline on the conversation message it belongs to.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-ASSET-1 | A generated image is stored to disk as a tracked asset. | `stores asset for image response` |
| ✅ | R-ASSET-2 | Generated audio is stored to disk as a tracked asset. | `stores asset for audio response` |
| ✅ | R-ASSET-3 | Generated video is stored to disk as a tracked asset. | `stores asset for video response` |
| ✅ | R-ASSET-4 | A text response produces no stored asset. | `skips asset for text response` |
| ✅ | R-ASSET-5 | Automatic asset storage can be turned off by configuration. | `respects auto_store_assets config` |
| ✅ | R-ASSET-6 | A stored asset links back to the call that produced it. | `sets execution_id from active execution` |
| ✅ | R-ASSET-7 | A tool-generated asset links back to the tool call that created it. | `sets tool_call_id column from active tool call` |
| ✅ | R-ASSET-8 | A tool-generated file is attached to the assistant message it belongs to. | `attaches tool-generated asset to assistant message via tool_call_id column` |
| ✅ | R-ASSET-9 | A media response hands back its content as base64. | `returns base64 encoded contents via toBase64` |
| ✅ | R-ASSET-10 | A media response stores itself to a configured disk on demand. | `stores to default disk with auto-generated path` |
| ❌ | R-ASSET-11 | A stored asset exposes a URL to its file. | src/Persistence/Models/Asset.php:128 — no test |

## Open questions

- Whether asset owner derivation from the conversation warrants its own guaranteed row.
