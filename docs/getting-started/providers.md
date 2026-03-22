# Providers

Atlas has its own provider layer with first-party drivers for major AI services. No external AI package required.

## Built-in Providers

<div class="full-width-table">

| Provider | Key | Capabilities |
|----------|-----|-------------|
| OpenAI | `openai` | Text, stream, structured, image, audio, video, embed, moderate, vision, tool calling |
| Anthropic | `anthropic` | Text, stream, structured, vision, tool calling |
| Google | `google` | Text, stream, structured, image, embed, vision, tool calling |
| xAI | `xai` | Text, stream, structured, image, audio, video, vision, tool calling |
| ElevenLabs | `elevenlabs` | Audio (TTS, STT, sound effects, music) |
| Cohere | `cohere` | Reranking |
| Jina | `jina` | Reranking, content extraction |

</div>

::: warning Model Dependent
Capability support may vary by model. Check with your provider for model-specific features.
:::

## Using Providers

### In Agents

Define `provider()` and `model()` on your agent class:

```php
use Atlasphp\Atlas\Agent;

class SupportAgent extends Agent
{
    public function provider(): ?string
    {
        return 'anthropic';
    }

    public function model(): ?string
    {
        return 'claude-sonnet-4-20250514';
    }
}
```

### Direct Calls

Use the facade to call providers directly without an agent:

```php
use Atlasphp\Atlas\Facades\Atlas;

// Text generation
$response = Atlas::text('openai', 'gpt-4o')
    ->message('Explain quantum computing in one paragraph.')
    ->asText();

// Image generation
$response = Atlas::image('openai', 'dall-e-3')
    ->message('A sunset over a mountain lake')
    ->asImage();

// Audio generation
$response = Atlas::audio('elevenlabs', 'eleven_multilingual_v2')
    ->message('Welcome to Atlas.')
    ->asAudio();

// Embeddings
$response = Atlas::embed('openai', 'text-embedding-3-small')
    ->message('The quick brown fox')
    ->asEmbed();

// Moderation
$response = Atlas::moderate('openai', 'omni-moderation-latest')
    ->message('Check this content')
    ->asModerate();

// Reranking
$response = Atlas::rerank('cohere', 'rerank-v3.5')
    ->query('search query')
    ->documents(['doc one', 'doc two'])
    ->asRerank();
```

### Runtime Override

Override the provider and model on any agent at call time:

```php
$response = Atlas::agent('support')
    ->withProvider('anthropic', 'claude-sonnet-4-20250514')
    ->message('Hello')
    ->asText();
```

## Custom Providers

Any OpenAI-compatible API can be used via the `chat_completions` driver. Add entries to the `providers` array in `config/atlas.php`:

```php
'providers' => [

    // Ollama (local)
    'ollama' => [
        'driver'   => 'chat_completions',
        'api_key'  => env('OLLAMA_API_KEY', 'ollama'),
        'base_url' => env('OLLAMA_URL', 'http://localhost:11434/v1'),
    ],

    // LM Studio (local)
    'lmstudio' => [
        'driver'   => 'chat_completions',
        'api_key'  => env('LMSTUDIO_API_KEY', 'lm-studio'),
        'base_url' => env('LMSTUDIO_URL', 'http://localhost:1234/v1'),
    ],

    // Groq
    'groq' => [
        'driver'   => 'chat_completions',
        'api_key'  => env('GROQ_API_KEY'),
        'base_url' => 'https://api.groq.com/openai/v1',
    ],

    // Together AI
    'together' => [
        'driver'   => 'chat_completions',
        'api_key'  => env('TOGETHER_API_KEY'),
        'base_url' => 'https://api.together.xyz/v1',
    ],

    // DeepSeek
    'deepseek' => [
        'driver'   => 'chat_completions',
        'api_key'  => env('DEEPSEEK_API_KEY'),
        'base_url' => 'https://api.deepseek.com/v1',
    ],

    // OpenRouter
    'openrouter' => [
        'driver'   => 'chat_completions',
        'api_key'  => env('OPENROUTER_API_KEY'),
        'base_url' => 'https://openrouter.ai/api/v1',
    ],

],
```

Use custom providers exactly like built-in ones:

```php
$response = Atlas::text('ollama', 'llama3')
    ->message('Hello')
    ->asText();

$response = Atlas::text('groq', 'llama-3.3-70b-versatile')
    ->message('Summarize this text.')
    ->asText();
```

## Provider Reference

Each provider is configured in `config/atlas.php` under the `providers` key. Add your API key to `.env` and you're ready to go.

---

### OpenAI

Text, images, audio, video, embeddings, moderation, structured output, streaming, vision, tool calling.

**Get your key:** [platform.openai.com/api-keys](https://platform.openai.com/api-keys) · [Docs](https://platform.openai.com/docs)

```env
OPENAI_API_KEY=sk-...
OPENAI_ORGANIZATION=org-...     # optional
OPENAI_URL=https://api.openai.com/v1  # optional, custom endpoint
```

```php
// config/atlas.php → providers
'openai' => [
    'api_key'      => env('OPENAI_API_KEY'),
    'url'          => env('OPENAI_URL', 'https://api.openai.com/v1'),
    'organization' => env('OPENAI_ORGANIZATION'),
],
```

**Popular models:** `gpt-4o`, `gpt-4o-mini`, `o3-mini`, `dall-e-3`, `tts-1`, `whisper-1`, `text-embedding-3-small`

---

### Anthropic

Text, structured output, streaming, vision, tool calling.

**Get your key:** [console.anthropic.com/settings/keys](https://console.anthropic.com/settings/keys) · [Docs](https://docs.anthropic.com)

```env
ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_VERSION=2024-10-22    # optional, API version
ANTHROPIC_URL=https://api.anthropic.com/v1  # optional
```

```php
'anthropic' => [
    'api_key' => env('ANTHROPIC_API_KEY'),
    'url'     => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
    'version' => env('ANTHROPIC_VERSION', '2024-10-22'),
],
```

**Popular models:** `claude-sonnet-4-20250514`, `claude-opus-4-20250514`, `claude-haiku-3-5-20241022`

---

### Google (Gemini)

Text, images, embeddings, structured output, streaming, vision, tool calling.

**Get your key:** [aistudio.google.com/apikey](https://aistudio.google.com/apikey) · [Docs](https://ai.google.dev/docs)

```env
GOOGLE_API_KEY=...
GOOGLE_URL=https://generativelanguage.googleapis.com  # optional
```

```php
'google' => [
    'api_key' => env('GOOGLE_API_KEY'),
    'url'     => env('GOOGLE_URL', 'https://generativelanguage.googleapis.com'),
],
```

**Popular models:** `gemini-2.5-flash`, `gemini-2.5-pro`, `text-embedding-004`

---

### xAI (Grok)

Text, images, audio, video, structured output, streaming, vision, tool calling.

**Get your key:** [console.x.ai](https://console.x.ai) · [Docs](https://docs.x.ai)

```env
XAI_API_KEY=...
XAI_URL=https://api.x.ai/v1  # optional
```

```php
'xai' => [
    'api_key' => env('XAI_API_KEY'),
    'url'     => env('XAI_URL', 'https://api.x.ai/v1'),
],
```

**Popular models:** `grok-3`, `grok-3-mini`, `grok-2-image`, `grok-2-video`

---

### ElevenLabs

Text-to-speech, speech-to-text, sound effects, music generation, voice cloning.

**Get your key:** [elevenlabs.io/app/settings/api-keys](https://elevenlabs.io/app/settings/api-keys) · [Docs](https://elevenlabs.io/docs)

```env
ELEVENLABS_API_KEY=...
ELEVENLABS_URL=https://api.elevenlabs.io/v1  # optional
```

```php
'elevenlabs' => [
    'api_key'       => env('ELEVENLABS_API_KEY'),
    'url'           => env('ELEVENLABS_URL', 'https://api.elevenlabs.io/v1'),
    'media_timeout' => 300,  // longer timeout for audio/music generation
],
```

**Popular models:** `eleven_multilingual_v2`, `eleven_turbo_v2_5`

---

### Cohere

Semantic reranking for RAG and search quality.

**Get your key:** [dashboard.cohere.com/api-keys](https://dashboard.cohere.com/api-keys) · [Docs](https://docs.cohere.com)

```env
COHERE_API_KEY=...
COHERE_URL=https://api.cohere.com  # optional
```

```php
'cohere' => [
    'api_key' => env('COHERE_API_KEY'),
    'url'     => env('COHERE_URL', 'https://api.cohere.com'),
],
```

**Popular models:** `rerank-v3.5`, `rerank-english-v3.0`, `rerank-multilingual-v3.0`

---

### Jina

Semantic reranking and content extraction.

**Get your key:** [jina.ai/api](https://jina.ai/api) · [Docs](https://docs.jina.ai)

```env
JINA_API_KEY=...
JINA_URL=https://api.jina.ai  # optional
```

```php
'jina' => [
    'api_key' => env('JINA_API_KEY'),
    'url'     => env('JINA_URL', 'https://api.jina.ai'),
],
```

**Popular models:** `jina-reranker-v2-base-multilingual`

### Default Provider & Model

Configure defaults per modality so you can omit provider/model from calls:

```php
'defaults' => [
    'text'     => ['provider' => env('ATLAS_TEXT_PROVIDER'), 'model' => env('ATLAS_TEXT_MODEL')],
    'image'    => ['provider' => env('ATLAS_IMAGE_PROVIDER'), 'model' => env('ATLAS_IMAGE_MODEL')],
    'video'    => ['provider' => env('ATLAS_VIDEO_PROVIDER'), 'model' => env('ATLAS_VIDEO_MODEL')],
    'embed'    => ['provider' => env('ATLAS_EMBED_PROVIDER'), 'model' => env('ATLAS_EMBED_MODEL')],
    'moderate' => ['provider' => env('ATLAS_MODERATE_PROVIDER'), 'model' => env('ATLAS_MODERATE_MODEL')],
    'rerank'   => ['provider' => env('ATLAS_RERANK_PROVIDER'), 'model' => env('ATLAS_RERANK_MODEL')],
],
```

## Provider Interrogation

Query provider capabilities and available models at runtime:

```php
use Atlasphp\Atlas\Facades\Atlas;

// List available models
$models = Atlas::provider('openai')->models();

// List available voices (ElevenLabs, OpenAI)
$voices = Atlas::provider('elevenlabs')->voices();

// Validate API key connectivity
$valid = Atlas::provider('openai')->validate();

// Query provider capabilities
$capabilities = Atlas::provider('openai')->capabilities();

// Get provider display name
$name = Atlas::provider('openai')->name();
```

## Next Steps

- [Configuration](/getting-started/configuration) — Full configuration reference
- [Agents](/features/agents) — Create agents with specific providers
- [Text](/modalities/text) — Text generation and streaming
