---
id: SPEECH
name: Speech
---

## What this is

Two-way spoken language: turning written text into a spoken voice, and turning a recording back
into text. Both directions are reached through the same surface.

## Why it exists

- Written text is voiced without a separate speech toolkit.
- A recording is turned into text through the same surface.
- Voice, speed, and language shape how the words are spoken.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-SPEECH-1 | Written text is turned into spoken audio. | `sends TTS request to /v1/audio/speech` |
| ✅ | R-SPEECH-2 | A caller-chosen voice speaks the text. | `uses custom voice and language` |
| ✅ | R-SPEECH-3 | The speaking speed is set by the caller. | `maps speed to voice_settings` |
| ✅ | R-SPEECH-4 | The spoken language is set by the caller. | `sends language_code from language` |
| ✅ | R-SPEECH-5 | The audio output format is chosen by the caller. | `uses custom format` |
| ✅ | R-SPEECH-6 | Spoken audio is transcribed to text. | `sends STT request to /v1/audio/transcriptions` |
| ✅ | R-SPEECH-7 | Transcription without audio input is refused. | `throws when no audio media is provided for transcription` |
| ❌ | R-SPEECH-8 | Expressive delivery controls apply where a provider supports them. | — |

## Open questions

- Whether expressive delivery tags deserve a guaranteed contract or remain a provider passthrough.
