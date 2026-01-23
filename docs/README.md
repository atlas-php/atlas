# Atlas Documentation

> Documentation index for the Atlas Laravel AI package.

---

## Quick Links

| Resource | Description |
|----------|-------------|
| [Usage Guide](./USAGE.md) | Primary facade usage patterns |
| [Installation Guide](./guides/installation.md) | Get started with Atlas |
| [Creating Agents](./guides/creating-agents.md) | Build custom AI agents |
| [Creating Tools](./guides/creating-tools.md) | Define tools for agents |
| [Multi-Turn Conversations](./guides/multi-turn-conversations.md) | Handle conversation context |
| [Extending Atlas](./guides/extending-atlas.md) | Pipeline middleware and extensions |
| [Testing](./guides/testing.md) | Unit and integration testing with Atlas::fake() |
| [Error Handling](./advanced/error-handling.md) | Exception types and retry strategies |
| [Streaming](./capabilities/streaming.md) | Real-time streaming with events |

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
    │       ├── withVariables() / withMetadata() → PendingAtlasRequest
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

For development guidelines, see the [AGENTS.md](https://github.com/atlasphp/atlas/blob/main/AGENTS.md) file in the repository.
