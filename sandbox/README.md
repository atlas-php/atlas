# Atlas Sandbox

A ChatGPT-style web UI that showcases what Atlas can do: multi-agent chat, tool
calling, conversation memory, and live image/video generation — all running
against real AI providers. It doubles as a harness for testing Atlas against
real APIs.

## Quick start

You need **PHP 8.2+**, **Composer**, and **Node 18+**. No database server
required — the sandbox uses SQLite out of the box.

```bash
cd sandbox

# 1. Configure — copy the template and add your keys
cp .env.example .env
#    Open .env and set OPENAI_API_KEY and XAI_API_KEY (enough for every agent).

# 2. Install dependencies
composer install
npm install

# 3. Create the database + demo user (one time)
composer setup

# 4. Start everything (web server + queue + WebSockets + Vite) in one command
composer dev
```

Then open **http://localhost:8000** and start chatting.

> `composer dev` runs the Laravel server, the queue worker, the Reverb
> WebSocket server, and the Vite dev server together. Press `Ctrl+C` to stop
> all of them. Restart it after changing PHP code so the queue worker picks up
> your changes.

## The agents

Pick an agent from the popover at the bottom-left of the message box. A
conversation is locked to its agent once it starts, so each thread stays with
one agent.

| Agent | Powered by | What it does |
|-------|------------|--------------|
| **Atlas** | OpenAI `gpt-4o` | General assistant with live web search |
| **Sage** | xAI `grok-4` | Reasoning specialist with web search |
| **Iris** | OpenAI `gpt-4o` + image tool | Generates images (xAI `grok-imagine-image`) |
| **Reel** | OpenAI `gpt-4o` + video tool | Generates short videos (xAI `grok-imagine-video`) |

Generated images and videos render inline in the chat. There's also a mic
button for a realtime voice agent.

Agents live in `app/Agents/` and are registered in
`app/Providers/SandboxServiceProvider.php`. The picker roster (order + icons)
is defined in `app/Support/ChatAgents.php`.

## Resetting

```bash
composer setup   # fresh database + demo user + cleared generated assets
```

## Using Postgres instead of SQLite

Only needed for the vector/embedding CLI test scripts (they require pgvector).
Uncomment the `pgsql` block in `.env`, create the database, then `composer setup`.

## Running provider test scripts

Standalone scripts that bootstrap the sandbox and run against real APIs:

```bash
php test-openai-provider.php     # OpenAI: text, streaming, structured output, tools, vision, audio, embeddings, moderation
php test-google-provider.php     # Google Gemini: text, streaming, structured output, tools, vision
php test-xai-provider.php        # xAI/Grok: text, streaming, structured output, tools
php test-middleware.php          # Middleware integration against OpenAI (all modalities)
php test-lmstudio-provider.php   # LM Studio (requires local instance running)
```

## API endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/chat` | Send a message (async via queue) |
| `GET` | `/api/agents` | List the selectable chat agents |
| `GET` | `/api/conversations` | List conversations |
| `GET` | `/api/conversations/{id}` | Conversation with messages |
| `DELETE` | `/api/conversations/{id}` | Delete conversation |
| `GET` | `/api/conversations/{id}/messages` | Paginated messages (`?before=&limit=`) |
| `POST` | `/api/conversations/{id}/retry` | Retry last response |
| `GET` | `/api/conversations/{id}/processing` | Typing/queued status |
| `GET` | `/api/executions/{id}` | Execution status |
| `GET` | `/api/assets/{id}.{ext}` | Asset file proxy |

## Code quality

```bash
composer lint       # Fix code style with Pint
composer lint:test  # Check code style without fixing
composer analyse    # Run PHPStan static analysis
composer check      # Run all checks (lint:test + analyse)
```

## Structure

```
sandbox/
├── bootstrap.php                       # Testbench app bootstrap (loads Atlas + env)
├── config/                             # app, atlas, broadcasting, cache, database, queue, …
├── app/
│   ├── Agents/                         # Atlas, Sage, Iris, Reel (+ VoiceAssistant)
│   ├── Tools/                          # GenerateImage, GenerateVideo, GenerateSpeech
│   ├── Support/ChatAgents.php          # Picker roster (order + icons)
│   ├── Console/FreshCommand.php        # `sandbox:fresh` reset (creates SQLite + seeds user)
│   ├── Http/Controllers/
│   │   ├── ChatController.php          # Chat + agents API (async queue + broadcasting)
│   │   ├── AssetController.php         # Asset file proxy
│   │   └── VoiceController.php         # Voice session
│   ├── Http/Resources/MessageResource.php
│   ├── Models/User.php
│   └── Providers/SandboxServiceProvider.php
├── database/migrations/                # (database.sqlite is created locally, not committed)
├── resources/
│   ├── css/app.css                     # Tailwind + theme tokens
│   ├── js/app.ts                       # Vue + Echo entry point
│   ├── js/App.vue                      # Two-column chat layout
│   ├── js/composables/                 # useChat, useAttachments, useVoice
│   └── js/components/
│       ├── ThreadSidebar.vue           # Conversation list
│       ├── ChatThread.vue              # Message container + infinite scroll
│       ├── ChatMessageBubble.vue       # Message rendering + execution trace
│       ├── ChatInput.vue               # Input + attachments + agent picker
│       ├── AgentPicker.vue             # Agent switcher popover
│       └── VoiceButton.vue             # Realtime voice toggle
├── routes/                             # web.php (SPA catch-all) + api.php
└── .env                                # API keys + config (not committed)
```
