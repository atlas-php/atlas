---
id: CONFIG
name: Configuration
---

## What this is

The single set of settings that decides which provider, model, and credentials a request uses, and
the one unified surface every capability is called through. Changing a setting, not code, is how a
developer swaps providers or models.

## Why it exists

- A developer builds against one consistent surface instead of stitching per-vendor toolkits together.
- Providers and models are swapped by changing a string, never by rewriting the application.
- Sensible defaults mean most calls carry no configuration at all.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-CONFIG-1 | Every capability is reached through its own method on one unified surface. | `text returns TextRequest` |
| ✅ | R-CONFIG-2 | A provider may be named as a plain string or given as a typed value. | `accepts Provider enum` |
| ✅ | R-CONFIG-3 | A modality call may omit provider and model when a default is configured for it. | `defaultFor returns provider and model` |
| ✅ | R-CONFIG-4 | A configured default resolves to a provider and its model, or to neither. | `defaultFor returns null when no default configured` |
| ✅ | R-CONFIG-5 | A call with no provider and no configured default is refused. | `throws AtlasException when no provider configured for text` |
| ✅ | R-CONFIG-6 | A refusal for a missing default names the setting that would satisfy it. | `includes env var hint in exception message` |
| ✅ | R-CONFIG-7 | All Atlas settings live in one published configuration file. | `merges the atlas config` |
| ✅ | R-CONFIG-8 | A missing setting falls back to a sensible built-in default. | `returns sensible defaults when config keys are missing` |
| ✅ | R-CONFIG-9 | A runtime configuration change takes effect on the next call after a refresh. | `clears the Atlas facade on refresh so runtime config changes apply to facade calls` |
| ✅ | R-CONFIG-10 | Retry and timeout limits are read from configuration. | `has retry values configured` |
| ✅ | R-CONFIG-11 | Media storage behaviour is read from configuration. | `reads storage config` |
| ✅ | R-CONFIG-12 | A per-request override never mutates the shared configuration. | `is immutable across all overrides` |

## Open questions

- Whether per-operation timeout classes (default, reasoning, media) warrant their own guaranteed row.
