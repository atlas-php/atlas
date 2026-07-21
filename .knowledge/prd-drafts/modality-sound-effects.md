---
id: SFX
name: Sound effects
---

## What this is

Sound-effect generation: turning a short written description into a one-off audio effect for games,
video, or ambient use.

## Why it exists

- A written description becomes a ready-to-use sound effect.
- Duration is set per request.
- Looping and prompt fidelity tune the result for games and ambient backgrounds.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-SFX-1 | A sound effect is generated from a written prompt. | `posts to /sound-generation with text` |
| ✅ | R-SFX-2 | The sound effect's duration is set by the caller. | `sends duration_seconds as float` |
| ✅ | R-SFX-3 | A sound effect loops seamlessly where a provider supports it. | `sends loop option` |
| ✅ | R-SFX-4 | How closely the effect follows the prompt is adjustable. | `sends prompt_influence` |
| ✅ | R-SFX-5 | A sound effect requires a prompt. | `throws when instructions is null` |

## Open questions

- Whether prompt fidelity should carry a documented range rather than a provider passthrough.
