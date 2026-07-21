---
id: AGENT
name: Agents
---

## What this is

A reusable definition that packages a provider, model, instructions, and tools into one named thing a developer calls. It carries sensible defaults, interpolates runtime variables into its instructions, and answers through several terminals.

## Why it exists

- A developer configures behaviour once and reuses it everywhere by name.
- Instructions personalise themselves from runtime values without rewriting the prompt.
- The same agent answers as text, a stream, structured data, or a live voice session.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-AGENT-1 | An agent with no overrides is valid, every setting falling back to a default. | `defaults provider to null` |
| ✅ | R-AGENT-2 | An agent's key is derived automatically from its class name. | `derives kebab-case key from class name, stripping Agent suffix` |
| ✅ | R-AGENT-3 | Agents are discovered automatically from a configured location. | `discovers agent classes from a directory` |
| ✅ | R-AGENT-4 | Every agent setting can be overridden at the moment of the call. | `runtime overrides take precedence over agent config` |
| ✅ | R-AGENT-5 | Instructions interpolate runtime variables before the prompt is sent. | `interpolates variables into agent instructions` |
| ✅ | R-AGENT-6 | A per-call variable overrides both a configured and a globally registered value. | `withVariables overrides config and registry` |
| ✅ | R-AGENT-7 | An unresolved placeholder is left unchanged in the output. | `leaves unknown placeholders as-is` |
| ✅ | R-AGENT-8 | A completed agent turn is available as text. | `executes asText without tools via direct driver call` |
| ✅ | R-AGENT-9 | An agent turn can be delivered as a stream. | `executes asStream without tools` |
| ✅ | R-AGENT-10 | An agent turn can return a structured result. | `executes asStructured` |
| ✅ | R-AGENT-11 | An agent can open a real-time voice session. | `asVoice returns a VoiceSession with the correct provider` |

## Open questions

- None.
