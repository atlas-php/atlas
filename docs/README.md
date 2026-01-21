# Atlas Documentation

> Documentation index for the Atlas Laravel AI package.

---

## Quick Links

| Resource | Description |
|----------|-------------|
| [Usage Guide](./USAGE.md) | Primary facade usage patterns |
| [Installation Guide](./guides/Installation.md) | Get started with Atlas |
| [Creating Agents](./guides/Creating-Agents.md) | Build custom AI agents |
| [Creating Tools](./guides/Creating-Tools.md) | Define tools for agents |
| [Multi-Turn Conversations](./guides/Multi-Turn-Conversations.md) | Handle conversation context |
| [Extending Atlas](./guides/Extending-Atlas.md) | Pipeline middleware and extensions |

---

## Technical Specifications

| Module | Description |
|--------|-------------|
| [SPEC-Foundation](./spec/SPEC-Foundation.md) | Pipeline registry, pipeline runner, extension registry |
| [SPEC-Providers](./spec/SPEC-Providers.md) | Prism builder, embedding, image, and speech services |
| [SPEC-Agents](./spec/SPEC-Agents.md) | Agent registry, resolver, executor, system prompt builder |
| [SPEC-Tools](./spec/SPEC-Tools.md) | Tool registry, executor, builder, parameters |
| [SPEC-Facade](./spec/SPEC-Facade.md) | Atlas facade and AtlasManager API |

---

## Architecture Overview

Atlas is a Laravel package providing AI capabilities through a clean, stateless API:

```
Atlas Facade
    │
    ├── AtlasManager (orchestration)
    │       ├── chat() → AgentResolver → AgentExecutor → Prism
    │       ├── forMessages() → MessageContextBuilder
    │       ├── embed() → EmbeddingService → Prism
    │       ├── image() → ImageService → Prism
    │       └── speech() → SpeechService → Prism
    │
    ├── Agents Module
    │       ├── AgentContract (definition interface)
    │       ├── AgentRegistry (storage)
    │       ├── AgentResolver (lookup)
    │       ├── AgentExecutor (execution)
    │       └── SystemPromptBuilder (interpolation)
    │
    ├── Tools Module
    │       ├── ToolContract (definition interface)
    │       ├── ToolRegistry (storage)
    │       ├── ToolExecutor (execution)
    │       └── ToolBuilder (Prism integration)
    │
    └── Foundation Module
            ├── PipelineRegistry (hook definitions)
            └── PipelineRunner (middleware execution)
```

---

## Core Concepts

### Stateless Design

Atlas is stateless by design. Consumer applications manage:
- Conversation history (database, session, etc.)
- User context and preferences
- Agent configurations per user/tenant

Atlas provides the execution infrastructure.

### Agents vs Tools

- **Agents** are AI personalities with specific configurations (provider, model, system prompt, tools)
- **Tools** are callable functions that agents can invoke during execution

### Pipeline System

Atlas uses a pipeline middleware system for extensibility:
- `agent.before_execute` / `agent.after_execute`
- `agent.system_prompt.before_build` / `agent.system_prompt.after_build`
- `tool.before_execute` / `tool.after_execute`

---

## Contributing

For development guidelines, see [AGENTS.md](../AGENTS.md) in the repository root.
