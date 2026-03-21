# Atlas Sandbox

Testing environment for validating Atlas v3 against real AI providers. Runs provider integration tests without a full Laravel application — uses Orchestra Testbench for a minimal runtime.

## Setup

```bash
cd sandbox
cp .env.example .env
# Add your API keys to .env
composer install
npm install
```

## Running Tests

Each test file is a standalone script that bootstraps the sandbox and runs against real APIs:

```bash
php test-openai-provider.php     # OpenAI: text, streaming, structured output, tools, vision, audio, embeddings, moderation
php test-google-provider.php     # Google Gemini: text, streaming, structured output, tools, vision
php test-xai-provider.php        # xAI/Grok: text, streaming, structured output, tools
php test-middleware.php           # Middleware integration against OpenAI (all modalities)
php test-lmstudio-provider.php   # LM Studio (requires local instance running)
```

## Web UI (Development)

The sandbox includes a Vue 3 + TypeScript + Tailwind CSS + shadcn-vue foundation for building a demo UI.

```bash
npm run dev     # Start Vite dev server
npm run build   # Production build
```

Add shadcn components as needed:

```bash
npx shadcn-vue@latest add button
npx shadcn-vue@latest add card
```

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
│   ├── database.php           # SQLite config
│   └── session.php            # Session config for web middleware
├── app/Providers/
│   └── SandboxServiceProvider.php  # Routes + views registration
├── resources/
│   ├── css/app.css            # Tailwind CSS with dark theme tokens
│   ├── js/app.ts              # Vue entry point
│   ├── js/App.vue             # Root Vue component
│   ├── js/lib/utils.ts        # shadcn-vue cn() utility
│   ├── js/components/ui/      # shadcn-vue components (add as needed)
│   ├── js/composables/        # Vue composables
│   └── views/app.blade.php    # Blade layout with Vite
├── routes/web.php             # SPA catch-all route
├── test-openai-provider.php   # OpenAI integration tests
├── test-google-provider.php   # Google Gemini integration tests
├── test-xai-provider.php      # xAI/Grok integration tests
├── test-middleware.php         # Middleware integration tests
├── test-lmstudio-provider.php # LM Studio (ChatCompletions driver) tests
└── .env                       # API keys (not committed)
```
