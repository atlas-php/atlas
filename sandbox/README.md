# Atlas Sandbox

Testing environment for validating Atlas v3 against real AI providers. Includes a ChatGPT-style web UI that showcases the full agent pipeline: conversations, tool calling, memory, and multi-modal generation.

## Setup

```bash
cd sandbox
cp .env.example .env
# Add your API keys to .env
composer install
npm install
php artisan migrate
```

## Running the Chat UI

You need three terminals:

```bash
# Terminal 1 — Vite dev server (hot reload)
npm run dev

# Terminal 2 — Laravel server
php artisan serve

# Terminal 3 — Queue worker (processes agent messages)
php artisan queue:work
```

Open **http://localhost:8000**

### Optional: WebSocket broadcasting

For real-time updates via Reverb instead of polling:

```bash
# Terminal 4 — Reverb WebSocket server
php artisan reverb:start
```

Without Reverb, the UI falls back to polling `/api/conversations/{id}/processing` every 2 seconds.

### Notes

- **Restart the queue worker after code changes** — it caches code on startup
- During development, `php artisan queue:listen` auto-reloads but is slower
- If using Herd, the app server (Terminal 2) is handled for you — just run Vite + queue worker

## Running Provider Tests

Each test file is a standalone script that bootstraps the sandbox and runs against real APIs:

```bash
php test-openai-provider.php     # OpenAI: text, streaming, structured output, tools, vision, audio, embeddings, moderation
php test-google-provider.php     # Google Gemini: text, streaming, structured output, tools, vision
php test-xai-provider.php        # xAI/Grok: text, streaming, structured output, tools
php test-middleware.php           # Middleware integration against OpenAI (all modalities)
php test-lmstudio-provider.php   # LM Studio (requires local instance running)
```

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/chat` | Send message (async via queue) |
| `GET` | `/api/conversations` | List conversations |
| `GET` | `/api/conversations/{id}` | Conversation with messages |
| `DELETE` | `/api/conversations/{id}` | Delete conversation |
| `GET` | `/api/conversations/{id}/messages` | Paginated messages (`?before=&limit=`) |
| `POST` | `/api/conversations/{id}/retry` | Retry last response |
| `GET` | `/api/conversations/{id}/processing` | Typing/queued status |
| `GET` | `/api/executions/{id}` | Execution status |
| `GET` | `/api/assets/{id}.{ext}` | Asset file proxy |

## Code Quality

```bash
composer lint       # Fix code style with Pint
composer lint:test  # Check code style without fixing
composer analyse    # Run PHPStan static analysis
composer check      # Run all checks (lint:test + analyse)
```

## Structure

```
sandbox/
├── bootstrap.php              # Testbench app bootstrap (loads Atlas + env)
├── config/
│   ├── app.php                # Basic Laravel config
│   ├── atlas.php              # v3 Atlas config with env bindings
│   ├── broadcasting.php       # Reverb broadcasting config
│   ├── cache.php              # Database cache config
│   ├── database.php           # PostgreSQL config
│   ├── filesystems.php        # Storage config
│   ├── queue.php              # Database queue config
│   └── session.php            # Session config
├── app/
│   ├── Agents/AssistantAgent.php        # Multi-modal agent with tools + memory
│   ├── Http/Controllers/
│   │   ├── ChatController.php           # Chat API (async queue + broadcasting)
│   │   └── AssetController.php          # Asset file proxy
│   ├── Http/Resources/MessageResource.php  # Message + execution trace formatter
│   ├── Models/User.php
│   ├── Providers/SandboxServiceProvider.php
│   └── Tools/                           # GenerateImage, GenerateVideo, GenerateSpeech
├── database/migrations/
├── resources/
│   ├── css/app.css                      # Tailwind + theme tokens + chat animations
│   ├── js/app.ts                        # Vue + Echo entry point
│   ├── js/echo.ts                       # Laravel Echo + Reverb config
│   ├── js/App.vue                       # Two-column chat layout
│   ├── js/utils/markdown.ts             # Marked + DOMPurify
│   ├── js/composables/
│   │   ├── useChat.ts                   # Chat state, API, polling, broadcasting
│   │   └── useAttachments.ts            # File attachment handling
│   ├── js/components/
│   │   ├── ThreadSidebar.vue            # Conversation list
│   │   ├── ChatThread.vue               # Message container + infinite scroll
│   │   ├── ChatMessageBubble.vue        # Message rendering + execution trace
│   │   ├── ChatInput.vue                # Input + file attachments
│   │   └── ChatTypingIndicator.vue      # Typing animation
│   └── views/app.blade.php
├── routes/
│   ├── web.php                          # SPA catch-all
│   └── api.php                          # Chat API routes
├── public/index.php                     # HTTP entry point
└── .env                                 # API keys (not committed)
```
