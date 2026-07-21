---
id: IMAGE
name: Images
---

## What this is

Image generation and understanding: turning a written prompt into pictures, and reading a text
description back out of an existing image. The same surface both creates images and edits them.

## Why it exists

- A prompt becomes a finished image without leaving the application.
- One request can return several images to choose from.
- An existing image can be described, edited, or restyled through the same surface.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-IMAGE-1 | An image is generated from a written prompt. | `sends image generation request to /v1/images/generations` |
| ✅ | R-IMAGE-2 | A generation request carries the caller's size, quality, and format preferences. | `builds request with correct values` |
| ✅ | R-IMAGE-3 | Requesting several images returns a set of images. | `returns multiple URLs when count > 1` |
| ✅ | R-IMAGE-4 | A provider's revised version of the prompt is returned where it offers one. | `sends text-to-image generation to /images/generations` |
| ✅ | R-IMAGE-5 | An existing image is described in text from vision input. | `dispatches asText to driver imageToText` |
| ✅ | R-IMAGE-6 | Describing an image is refused where a provider offers no such support. | `throws UnsupportedFeatureException for imageToText` |
| ✅ | R-IMAGE-7 | An existing image is edited or restyled from a reference where supported. | `routes to /images/edits as multipart when reference media is present` |
| ✅ | R-IMAGE-8 | Requesting image generation from a provider that lacks it is refused. | `throws when image capability is unsupported` |

## Open questions

- Whether returning an image as inline data rather than a hosted link warrants its own guaranteed row.
