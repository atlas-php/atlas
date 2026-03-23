# Custom Providers (OpenAI Compatible)

Atlas supports any OpenAI-compatible API through the `chat_completions` or `responses` drivers. This includes local inference servers like Ollama, LM Studio, and cloud services like Groq, Together, DeepSeek, and OpenRouter.

No custom driver code needed — just add config and go.

## How It Works

Many AI services expose an OpenAI-compatible `/v1/chat/completions` endpoint. Atlas's `chat_completions` driver speaks this protocol, so any compatible service can be used as a provider.

## Setting Up Ollama (Local)

[Ollama](https://ollama.ai) runs models locally on your machine.

### 1. Install and Start Ollama

```bash
# Install Ollama (macOS)
brew install ollama

# Pull a model
ollama pull llama3.2

# Ollama runs automatically on localhost:11434
```

### 2. Add to Atlas Config

```php
// config/atlas.php → providers
'ollama' => [
    'driver' => 'chat_completions',
    'api_key' => env('OLLAMA_API_KEY', 'ollama'),
    'base_url' => env('OLLAMA_URL', 'http://localhost:11434/v1'),
],
```

### 3. Use It

```php
$response = Atlas::text('ollama', 'llama3.2')
    ->instructions('You are a helpful assistant.')
    ->message('What is Laravel?')
    ->asText();
```

## Setting Up LM Studio (Local)

[LM Studio](https://lmstudio.ai) provides a desktop app for running local models with an OpenAI-compatible server.

### 1. Start the Server

Open LM Studio, load a model, and start the local server (default: `http://localhost:1234/v1`).

### 2. Add to Atlas Config

```php
'lmstudio' => [
    'driver' => 'chat_completions',
    'api_key' => env('LMSTUDIO_API_KEY', 'lm-studio'),
    'base_url' => env('LMSTUDIO_URL', 'http://localhost:1234/v1'),
],
```

### 3. Use It

```php
$response = Atlas::text('lmstudio', 'my-local-model')
    ->message('Hello from LM Studio')
    ->asText();
```

## Setting Up Cloud Services

### Groq

[Groq](https://groq.com) provides fast inference for open-source models.

```php
'groq' => [
    'driver' => 'chat_completions',
    'api_key' => env('GROQ_API_KEY'),
    'base_url' => 'https://api.groq.com/openai/v1',
],
```

```env
GROQ_API_KEY=gsk_...
```

```php
$response = Atlas::text('groq', 'llama-3.3-70b-versatile')
    ->message('Hello from Groq')
    ->asText();
```

### Together AI

```php
'together' => [
    'driver' => 'chat_completions',
    'api_key' => env('TOGETHER_API_KEY'),
    'base_url' => 'https://api.together.xyz/v1',
],
```

### DeepSeek

```php
'deepseek' => [
    'driver' => 'chat_completions',
    'api_key' => env('DEEPSEEK_API_KEY'),
    'base_url' => 'https://api.deepseek.com/v1',
],
```

### OpenRouter

[OpenRouter](https://openrouter.ai) provides access to hundreds of models through a single API.

```php
'openrouter' => [
    'driver' => 'chat_completions',
    'api_key' => env('OPENROUTER_API_KEY'),
    'base_url' => 'https://openrouter.ai/api/v1',
],
```

```php
$response = Atlas::text('openrouter', 'anthropic/claude-sonnet-4-20250514')
    ->message('Hello via OpenRouter')
    ->asText();
```

### Perplexity

```php
'perplexity' => [
    'driver' => 'chat_completions',
    'api_key' => env('PERPLEXITY_API_KEY'),
    'base_url' => 'https://api.perplexity.ai',
],
```

### Mistral

```php
'mistral' => [
    'driver' => 'chat_completions',
    'api_key' => env('MISTRAL_API_KEY'),
    'base_url' => 'https://api.mistral.ai/v1',
],
```

## Using the Responses Driver

Some providers use OpenAI's Responses API instead of Chat Completions. Use the `responses` driver:

```php
'custom_responses' => [
    'driver' => 'responses',
    'api_key' => env('CUSTOM_API_KEY'),
    'base_url' => env('CUSTOM_URL'),
],
```

## Capabilities

Custom providers using the `chat_completions` driver support:

| Feature | Supported |
|---------|-----------|
| Text generation | Yes |
| Streaming | Yes |
| Structured output | Yes |
| Tool calling | Yes (if the model supports it) |
| Vision | Yes (if the model supports it) |
| Image generation | No |
| Audio | No |
| Video | No |
| Embeddings | No |

::: tip Feature Availability
Features like tool calling, structured output, and vision depend on the model, not Atlas. If the model supports function calling via the OpenAI-compatible protocol, it will work through Atlas.
:::

## Capability Overrides

Override the default capabilities for a custom provider in `config/atlas.php`:

```php
'ollama' => [
    'driver' => 'chat_completions',
    'api_key' => 'ollama',
    'base_url' => 'http://localhost:11434/v1',
    'capabilities' => [
        'vision' => true,
        'toolCalling' => true,
    ],
],
```

## Using Custom Providers in Agents

```php
use Atlasphp\Atlas\Agent;

class LocalAgent extends Agent
{
    public function provider(): ?string
    {
        return 'ollama';
    }

    public function model(): ?string
    {
        return 'llama3.2';
    }

    public function instructions(): ?string
    {
        return 'You are a helpful local assistant.';
    }
}
```

```php
$response = Atlas::agent('local')
    ->message('What can you help me with?')
    ->asText();
```

## Mixing Providers

Use cloud and local providers side by side:

```php
// Cloud provider for complex tasks
$analysis = Atlas::text('openai', 'gpt-4o')
    ->instructions('Analyze this data thoroughly.')
    ->message($complexInput)
    ->asText();

// Local provider for simple tasks (free, private)
$summary = Atlas::text('ollama', 'llama3.2')
    ->instructions('Summarize this text briefly.')
    ->message($simpleInput)
    ->asText();
```
