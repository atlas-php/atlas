# Atlas Sandbox

The sandbox provides a testing environment for validating Atlas API functionality against real AI providers. It uses file-based storage (no database required) and provides artisan commands that serve as both testing utilities and implementation examples.

## Setup

1. Install sandbox dependencies:

```bash
cd sandbox
composer install
```

2. Copy the environment file and configure your API keys:

```bash
cp .env.example .env
# Edit .env with your API keys
```

3. Run commands:

```bash
php artisan atlas:chat
```

## Available Commands

### `atlas:chat` - Interactive Chat

Start an interactive chat session with an agent.

```bash
# Start new chat with default agent
php artisan atlas:chat

# Use specific agent
php artisan atlas:chat tool-demo

# Continue existing thread
php artisan atlas:chat --thread=550e8400-e29b-41d4-a716-446655440000

# Force new thread
php artisan atlas:chat --new

# List all saved threads
php artisan atlas:chat --list
```

**In-chat Commands:**
- `exit` / `quit` - Save and exit
- `clear` - Clear thread messages
- `history` - Show conversation history
- `save` - Save thread

**Output Example:**
```
=== Atlas Chat Sandbox ===
Agent: general-assistant (openai/gpt-4o)
Thread: 550e8400-e29b-41d4-a716-446655440000
Commands: exit, clear, history, save

You> Hello!

Assistant> Hello! How can I help you today?

--- Response Details ---
Tokens: 45 prompt / 12 completion / 57 total
Finish: stop
Tool Calls: 0
------------------------
```

### `atlas:embed` - Test Embeddings

Generate and analyze text embeddings.

```bash
# Single text
php artisan atlas:embed "Hello world"

# Batch mode (interactive)
php artisan atlas:embed --batch

# From file (one text per line)
php artisan atlas:embed --file=texts.txt

# Show configured dimensions only
php artisan atlas:embed --dimensions
```

**Output Example:**
```
=== Atlas Embedding Test ===
Provider: openai
Model: text-embedding-3-small
Dimensions: 1536

Input: "Hello world"

Embedding Vector:
  Dimensions: 1536
  First 5 values: [0.0123, -0.0456, 0.0789, -0.0012, 0.0345]
  Last 5 values: [-0.0234, 0.0567, -0.0890, 0.0123, -0.0456]
  Magnitude: 1.0000
  Non-zero: 1536 (100%)

--- Verification ---
[PASS] Vector has expected dimensions (1536)
[PASS] Vector is normalized (magnitude ~1.0)
[PASS] Values are within expected range [-1, 1]
```

### `atlas:image` - Test Image Generation

Generate images from text prompts.

```bash
# Basic generation
php artisan atlas:image "A serene mountain landscape at sunset"

# With options
php artisan atlas:image "A cat" --size=1024x1024 --quality=hd

# Save to file
php artisan atlas:image "A cat" --save=cat.png
```

**Output Example:**
```
=== Atlas Image Generation Test ===
Provider: openai
Model: dall-e-3

Prompt: "A serene mountain landscape at sunset"
Size: 1024x1024
Quality: standard

--- Response ---
URL: https://oaidalleapiprodscus.blob.core.windows.net/...
Revised Prompt: "A breathtaking mountain landscape..."
Base64: [available - 1.2MB]

Saved to: storage/outputs/mountain-sunset.png

--- Verification ---
[PASS] Response contains valid URL
[PASS] Response contains revised prompt
[PASS] Base64 data is valid image format
```

### `atlas:speech` - Test Text-to-Speech & Transcription

Convert text to speech or transcribe audio.

```bash
# Text to speech
php artisan atlas:speech --generate="Hello, this is a test"

# With voice selection
php artisan atlas:speech --generate="Hello" --voice=nova --format=mp3

# Transcribe audio
php artisan atlas:speech --transcribe=speech-123.mp3
```

**Output Example (TTS):**
```
=== Atlas Speech Test (TTS) ===
Provider: openai
Model: tts-1
Voice: nova
Format: mp3

Input Text: "Hello, this is a test of text to speech."

--- Response ---
Audio Size: 45.2 KB
Format: mp3
Duration: ~3 seconds (estimated)

Saved to: storage/outputs/speech-1705849200.mp3

--- Verification ---
[PASS] Audio data returned
[PASS] Format matches requested format
[PASS] Audio file is playable
```

### `atlas:vision` - Test Multimodal Attachments

Test image, document, audio, and video attachments with vision-capable models.

```bash
# Single image test with OpenAI
php artisan atlas:vision --provider=openai

# Single image test with Anthropic
php artisan atlas:vision --provider=anthropic

# Single image test with Gemini
php artisan atlas:vision --provider=gemini

# Multi-turn conversation with attachments
php artisan atlas:vision --provider=openai --thread

# Test all providers
php artisan atlas:vision --all-providers

# Run comprehensive test suite
php artisan atlas:vision --comprehensive

# Use base64 encoding instead of file path
php artisan atlas:vision --provider=openai --base64

# Generate test assets (images)
php artisan atlas:vision --generate-assets
```

**Output Example:**
```
Testing: openai-vision (openai/gpt-4o)

Image: /storage/outputs/test-apple.png

Prompt: Describe this image in detail...

=== Response ===
The image shows a vibrant red apple with a smooth, glossy surface...

--- Details ---
Tokens: 832 prompt / 114 completion / 946 total
Finish: stop
```

**Comprehensive Test Suite:**
- Single image analysis with each provider
- Multiple images in one request
- Base64 encoded images
- Conversation history with attachments
- Context construction verification

### `atlas:tools` - Test Tool Execution

Test agent tool calling capabilities.

```bash
# Default prompts (cycles randomly)
php artisan atlas:tools

# Specific agent
php artisan atlas:tools --agent=tool-demo

# Custom prompt
php artisan atlas:tools --prompt="What is 42 * 17?"
```

**Output Example:**
```
=== Atlas Tool Execution Test ===
Agent: tool-demo
Available Tools: calculator, weather, datetime

Prompt: "What is 42 * 17?"

--- Tool Calls ---
Tool #1: calculator
  Arguments: {"operation": "multiply", "a": 42, "b": 17}
  Result: "714"

--- Final Response ---
"42 multiplied by 17 equals 714."

--- Token Usage ---
Prompt: 125 | Completion: 45 | Total: 170

--- Verification ---
[PASS] Tool was called with correct name
[PASS] Tool arguments match expected schema
[PASS] Tool returned valid result
[PASS] Agent incorporated tool result in response

Duration: 1.234s
```

### `atlas:structured` - Test Structured Output

Extract structured data from text using schemas.

```bash
# Use predefined schema
php artisan atlas:structured --schema=person
php artisan atlas:structured --schema=product
php artisan atlas:structured --schema=review

# Custom prompt with schema
php artisan atlas:structured --schema=person --prompt="Extract: Jane Doe is 28 years old"
```

**Predefined Schemas:**
- `person` - name, age, email, occupation
- `product` - name, price, category, inStock
- `review` - rating, title, body, pros, cons

**Output Example:**
```
=== Atlas Structured Output Test ===
Agent: structured-output
Schema: person

Prompt: "Extract person info: John Smith is a 35-year-old software engineer at john@example.com"

--- Schema Definition ---
{
  "type": "object",
  "properties": {
    "name": {"type": "string"},
    "age": {"type": "number"},
    "email": {"type": "string"},
    "occupation": {"type": "string"}
  },
  "required": ["name", "age"]
}

--- Structured Response ---
{
  "name": "John Smith",
  "age": 35,
  "email": "john@example.com",
  "occupation": "software engineer"
}

--- Verification ---
[PASS] Response matches schema structure
[PASS] Required fields present: name, age
[PASS] Field types are correct
[PASS] No extra fields in response
```

## Available Agents

| Agent Key | Provider | Model | Description |
|-----------|----------|-------|-------------|
| `general-assistant` | openai | gpt-4o | General-purpose chat |
| `tool-demo` | openai | gpt-4o | Agent with tools |
| `structured-output` | openai | gpt-4o | Structured data extraction |
| `openai-vision` | openai | gpt-4o | Vision/multimodal image analysis |
| `anthropic-vision` | anthropic | claude-sonnet-4 | Vision/multimodal image analysis |
| `gemini-vision` | gemini | gemini-2.0-flash | Vision/multimodal image analysis |

## Available Tools

| Tool | Description |
|------|-------------|
| `calculator` | Basic math operations (add, subtract, multiply, divide) |
| `weather` | Mock weather lookup (simulated data) |
| `datetime` | Current date/time in various formats and timezones |

## Thread Storage

Chat threads are stored as JSON files in `storage/threads/`:

```json
{
  "uuid": "550e8400-e29b-41d4-a716-446655440000",
  "agent": "general-assistant",
  "created_at": "2024-01-21T10:00:00+00:00",
  "updated_at": "2024-01-21T10:05:00+00:00",
  "messages": [
    {"role": "user", "content": "Hello!"},
    {"role": "assistant", "content": "Hello! How can I help you today?"}
  ],
  "metadata": {
    "total_tokens": 57,
    "message_count": 2
  }
}
```

## Output Files

Generated files (images, audio) are saved to `storage/outputs/`.

## Configuration

The sandbox uses the main Atlas configuration from `config/atlas.php`. Environment variables in `sandbox/.env` override the defaults:

```env
# Provider API Keys
OPENAI_API_KEY=your-key-here
ANTHROPIC_API_KEY=your-key-here

# Default providers/models
ATLAS_CHAT_PROVIDER=openai
ATLAS_CHAT_MODEL=gpt-4o
ATLAS_EMBEDDING_PROVIDER=openai
ATLAS_EMBEDDING_MODEL=text-embedding-3-small
```

## Directory Structure

```
sandbox/
├── artisan                     # Console entry point
├── bootstrap.php               # Laravel bootstrap
├── .env.example               # Environment template
├── README.md                  # This file
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       ├── ChatCommand.php
│   │       ├── EmbedCommand.php
│   │       ├── ImageCommand.php
│   │       ├── SpeechCommand.php
│   │       ├── StructuredCommand.php
│   │       └── ToolsCommand.php
│   ├── Providers/
│   │   └── SandboxServiceProvider.php
│   └── Services/
│       ├── Agents/
│       │   ├── GeneralAssistantAgent.php
│       │   ├── ToolDemoAgent.php
│       │   └── StructuredOutputAgent.php
│       ├── Tools/
│       │   ├── CalculatorTool.php
│       │   ├── DateTimeTool.php
│       │   └── WeatherTool.php
│       └── ThreadStorageService.php
└── storage/
    ├── outputs/               # Generated files
    └── threads/               # Chat thread JSON files
```

## Creating Custom Agents

```php
<?php

namespace App\Services\Agents;

use Atlasphp\Atlas\Agents\AgentDefinition;

class MyCustomAgent extends AgentDefinition
{
    public function provider(): string
    {
        return 'openai';
    }

    public function model(): string
    {
        return 'gpt-4o';
    }

    public function systemPrompt(): string
    {
        return 'Your custom instructions here.';
    }
}
```

Register in `App\Providers\SandboxServiceProvider::registerAgents()`.

## Creating Custom Tools

```php
<?php

namespace App\Services\Tools;

use Atlasphp\Atlas\Tools\ToolDefinition;
use Atlasphp\Atlas\Tools\Support\ToolContext;
use Atlasphp\Atlas\Tools\Support\ToolParameter;
use Atlasphp\Atlas\Tools\Support\ToolResult;

class MyCustomTool extends ToolDefinition
{
    public function name(): string
    {
        return 'my_tool';
    }

    public function description(): string
    {
        return 'What this tool does.';
    }

    public function parameters(): array
    {
        return [
            ToolParameter::string('input', 'The input text', required: true),
        ];
    }

    public function handle(array $args, ToolContext $context): ToolResult
    {
        $result = doSomething($args['input']);
        return ToolResult::text($result);
    }
}
```

Register in `App\Providers\SandboxServiceProvider::registerTools()`.
