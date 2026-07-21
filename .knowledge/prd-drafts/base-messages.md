---
id: MESSAGE
name: Messages
---

## What this is

The typed building blocks a conversation is made of — messages tagged by who authored them, and the multimodal inputs those messages carry from wherever the content lives.

## Why it exists

- A developer composes a conversation from typed parts instead of hand-shaping per-provider payloads.
- The same input works whether it comes from a link, a file, raw bytes, or the app's own storage.
- A malformed message or an unreachable input fails loudly rather than silently.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-MESSAGE-1 | A message carries a typed role identifying its author. | `creates a UserMessage with correct role and default media` |
| ✅ | R-MESSAGE-2 | An assistant message can carry the tool calls the model requested. | `creates an AssistantMessage with tool calls` |
| ✅ | R-MESSAGE-3 | A raw message array is normalized into its typed message by role. | `converts user array to UserMessage` |
| ✅ | R-MESSAGE-4 | A message array with an unrecognized role is rejected. | `throws for invalid role string` |
| ✅ | R-MESSAGE-5 | An input is typed by modality as image, audio, video, or document. | `returns correct default mime types` |
| ✅ | R-MESSAGE-6 | A media input may be sourced from a url, path, base64, provider file, storage, or upload. | `creates an Image from storage` |
| ✅ | R-MESSAGE-7 | An input resolves a default content type when its source declares none. | `Image defaults to jpg for unknown mime` |
| ✅ | R-MESSAGE-8 | A stored input reads its contents back from storage rather than its original source. | `reads from storage after store instead of original source` |
| ✅ | R-MESSAGE-9 | An input with no source is rejected. | `throws when no source is set` |
| ✅ | R-MESSAGE-10 | An input pointing at a missing file is rejected. | `throws when file path does not exist` |

## Open questions

- None.
