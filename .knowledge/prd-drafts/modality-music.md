---
id: MUSIC
name: Music
---

## What this is

Music generation: turning a written description, or a structured plan of timed sections, into an
audio track.

## Why it exists

- A written description becomes a finished music track.
- A section-by-section plan gives control over how the piece unfolds.
- Duration and format are set per request.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-MUSIC-1 | Music is generated from a written prompt. | `posts to /music with prompt` |
| ✅ | R-MUSIC-2 | The music's duration is set by the caller. | `converts duration from seconds to milliseconds` |
| ✅ | R-MUSIC-3 | The music's output format is chosen by the caller. | `sends output_format as query parameter` |
| ✅ | R-MUSIC-4 | A structured composition plan may be supplied in place of a prompt. | `sends composition_plan instead of prompt when provided` |
| ✅ | R-MUSIC-5 | Music generation requires a prompt or a composition plan. | `throws when instructions is null and no composition_plan` |

## Open questions

- Whether per-section strict timing of a composition plan warrants its own guaranteed row.
