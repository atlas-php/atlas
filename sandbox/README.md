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

### `atlas:local-chat` - Chat with Local LLMs

Interactive chat with local LLM servers (LM Studio, Ollama, etc.) that provide an OpenAI-compatible API.

```bash
# Use default settings from .env (OLLAMA_URL, OLLAMA_MODEL)
php artisan atlas:local-chat

# Specify custom URL and model
php artisan atlas:local-chat --url=http://localhost:1234/v1 --model=llama3

# With custom system prompt
php artisan atlas:local-chat --system="You are a coding assistant."
```

**Configuration:**
```env
# Add to .env
OLLAMA_URL=http://localhost:1234/v1
OLLAMA_MODEL=your-model-name
```

**In-chat Commands:**
- `exit` / `quit` - Exit the chat
- `clear` - Clear conversation history
- `history` - Show conversation history

**Output Example:**
```
=== Atlas Local LLM Chat ===
URL: http://localhost:1234/v1
Model: llama3
Commands: exit, clear, history

Connecting to http://localhost:1234/v1...
Connected successfully!

You> Hello!

Assistant> Hello! How can I help you today?

--- Response Details ---
Tokens: 15 prompt / 8 completion / 23 total
Finish: stop
------------------------
```

**Supported Local LLM Servers:**
- [LM Studio](https://lmstudio.ai/) - GUI for running local models
- [Ollama](https://ollama.ai/) - Run models locally
- [LocalAI](https://localai.io/) - Self-hosted OpenAI alternative
- Any server with OpenAI-compatible API at `/v1/chat/completions`

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

### `atlas:comprehensive-tools` - Comprehensive Tool Verification

Test that all expected tools are called with detailed verification output.

```bash
# Test with Atlas tools only
php artisan atlas:comprehensive-tools

# Strict verification (fail if any tool not called)
php artisan atlas:comprehensive-tools --verify-all

# Custom prompt
php artisan atlas:comprehensive-tools --prompt="Calculate 100/5, get Paris weather, show current time"
```

**Output Example:**
```
=== Atlas Comprehensive Tools Test ===
Agent: comprehensive-tool
Available Tools: calculator, weather, datetime

Prompt: "Please demonstrate ALL your available tools..."

=== Tool Execution Verification ===

Expected Tools: calculator, weather, datetime
Called Tools: calculator, weather, datetime

--- Tool Call Details ---
Step 1: calculator
  Args: {"operation": "multiply", "a": 42, "b": 17}
  Result: "714"

Step 2: weather
  Args: {"location": "Paris", "units": "celsius"}
  Result: {"temperature": 18, "conditions": "partly cloudy"}

Step 3: datetime
  Args: {"timezone": "UTC", "format": "full"}
  Result: {"datetime": "2024-01-26 15:30:00", "timezone": "UTC"}

--- Verification Results ---
[PASS] All 3 expected tools were called
[PASS] Agent provided text response
```

### `atlas:pipeline` - Pipeline Context Manipulation

Test pipeline middleware patterns for context injection, tool filtering, and execution logging.

```bash
# Run all demos
php artisan atlas:pipeline --demo=all

# Test context injection only
php artisan atlas:pipeline --demo=context

# Test tool filtering only
php artisan atlas:pipeline --demo=tools

# Test execution logging only
php artisan atlas:pipeline --demo=log
```

**Available Demos:**
- `context` - Inject metadata into agent context, visible in `$response->metadata()`
- `tools` - Filter available tools to an allowlist
- `log` - Observe execution details for logging/auditing

**Output Example (Context Demo):**
```
--- Demo: Context Injection ---

This demo shows how pipelines can inject metadata into the agent context.
The InjectMetadataHandler adds custom metadata before agent execution.

Prompt: "What is 2 + 2?"

=== Response ===
2 + 2 equals 4.

=== Injected Metadata ===
  injected_at: 2024-01-26T15:30:00+00:00
  request_id: req_65b3a1f2e4b0c
  pipeline_demo: true
  custom_data: {"user_tier":"premium","rate_limit":100}

[PASS] Context injection demo completed
```

### `atlas:mcp` - MCP Tools Integration

Test MCP (Model Context Protocol) tools via Prism Relay integration.

```bash
# List configured MCP servers
php artisan atlas:mcp --list-servers

# List tools from a server
php artisan atlas:mcp --server=filesystem --list-tools

# Test MCP tools with prompt
php artisan atlas:mcp --server=filesystem --prompt="List files in the storage directory"
```

**Note:** Requires `prism-php/relay` package to be installed. The command gracefully handles cases where Relay is not available.

**Output Example:**
```
=== Atlas MCP Tools Test ===

Configured MCP Servers:

| Name       | Type  | Command/URL                    |
|------------|-------|--------------------------------|
| filesystem | stdio | npx @anthropic/mcp-filesystem  |

Tools from server: filesystem

| Name          | Description                           |
|---------------|---------------------------------------|
| read_file     | Read contents of a file               |
| write_file    | Write contents to a file              |
| list_directory| List files and directories            |

Total: 3 tools
```

## Available Agents

| Agent Key | Provider | Model | Description |
|-----------|----------|-------|-------------|
| `general-assistant` | openai | gpt-4o | General-purpose chat |
| `local-l-m` | openai | (from OLLAMA_MODEL env) | Local LLM via OpenAI-compatible API |
| `tool-demo` | openai | gpt-4o | Agent with tools |
| `comprehensive-tool` | openai | gpt-4o | Agent requiring ALL tools be demonstrated |
| `full-featured` | openai | gpt-4o | Demo of Atlas + Provider + MCP tools |
| `structured-output` | openai | gpt-4o | Structured data extraction |
| `openai-vision` | openai | gpt-4o | Vision/multimodal image analysis |
| `openai-web-search` | openai | gpt-4o | Agent with web search provider tool |
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
│   ├── Agents/
│   │   ├── ComprehensiveToolAgent.php
│   │   ├── FullFeaturedAgent.php
│   │   ├── GeneralAssistantAgent.php
│   │   ├── LocalLMAgent.php
│   │   ├── ToolDemoAgent.php
│   │   └── StructuredOutputAgent.php
│   ├── Console/
│   │   └── Commands/
│   │       ├── ChatCommand.php
│   │       ├── ComprehensiveToolsCommand.php
│   │       ├── EmbedCommand.php
│   │       ├── ImageCommand.php
│   │       ├── LocalChatCommand.php
│   │       ├── McpCommand.php
│   │       ├── PipelineCommand.php
│   │       ├── SpeechCommand.php
│   │       ├── StructuredCommand.php
│   │       ├── ToolsCommand.php
│   │       └── VisionCommand.php
│   ├── Pipelines/
│   │   ├── FilterToolsHandler.php
│   │   ├── InjectMetadataHandler.php
│   │   └── LogExecutionHandler.php
│   ├── Providers/
│   │   └── SandboxServiceProvider.php
│   ├── Services/
│   │   └── ThreadStorageService.php
│   └── Tools/
│       ├── CalculatorTool.php
│       ├── DateTimeTool.php
│       └── WeatherTool.php
└── storage/
    ├── outputs/               # Generated files
    └── threads/               # Chat thread JSON files
```

## Creating Custom Agents

```php
<?php

namespace App\Agents;

use Atlasphp\Atlas\Agents\AgentDefinition;

class MyCustomAgent extends AgentDefinition
{
    public function provider(): ?string
    {
        return 'openai';
    }

    public function model(): ?string
    {
        return 'gpt-4o';
    }

    public function systemPrompt(): ?string
    {
        return 'Your custom instructions here.';
    }
}
```

Agents are auto-discovered from `app/Agents/` via the atlas.php configuration.

## Creating Custom Tools

```php
<?php

namespace App\Tools;

use Atlasphp\Atlas\Tools\Support\ToolContext;
use Atlasphp\Atlas\Tools\Support\ToolParameter;
use Atlasphp\Atlas\Tools\Support\ToolResult;
use Atlasphp\Atlas\Tools\ToolDefinition;

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
            ToolParameter::string('input', 'The input text'),
        ];
    }

    public function handle(array $params, ToolContext $context): ToolResult
    {
        $result = doSomething($params['input']);
        return ToolResult::text($result);
    }
}
```

Tools are auto-discovered from `app/Tools/` via the atlas.php configuration.
