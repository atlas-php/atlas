---
id: VIDEO
name: Video
---

## What this is

Video generation and understanding: turning a prompt or a starting image into a moving clip, and
reading a text description back out of an existing video.

## Why it exists

- A prompt or a still image becomes a finished video clip.
- A long-running generation completes without the caller polling by hand.
- An existing video can be described through the same surface.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-VIDEO-1 | A video is generated from a written prompt. | `dispatches asVideo to driver` |
| ✅ | R-VIDEO-2 | A generated video's target duration follows the caller's request. | `maps duration to seconds string` |
| ✅ | R-VIDEO-3 | A generated video's aspect ratio follows the caller's request. | `maps ratio to size` |
| ✅ | R-VIDEO-4 | A generated video's output format follows the caller's request. | `builds request with correct values` |
| ✅ | R-VIDEO-5 | A video is generated from a starting image. | `sends input_reference for image-to-video` |
| ✅ | R-VIDEO-6 | An existing video is described in text from video input. | `dispatches asText to driver videoToText` |
| ✅ | R-VIDEO-7 | Describing a video is refused where a provider offers no such support. | `videoToText throws UnsupportedFeatureException` |
| ✅ | R-VIDEO-8 | A long generation is awaited until it completes. | `posts to /v1/videos and polls until completed` |
| ✅ | R-VIDEO-9 | A failed generation is surfaced as an error. | `throws ProviderException when video generation fails` |
| ✅ | R-VIDEO-10 | A generation error names the provider that produced it. | `attributes a handler-built error to the configured provider key, not openai (regression)` |

## Open questions

- Whether the wait interval between generation checks warrants a guaranteed, caller-visible row.
