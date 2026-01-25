# Providers

Atlas supports all AI providers available through Prism. Configure your preferred provider and use it with any Atlas agent or capability.

::: tip Prism Reference
For detailed provider configuration, API keys, and model-specific options, see the [Prism Providers documentation](https://prismphp.com/providers/openai.html).
:::

## Available Providers

Atlas has first-party support for these AI providers:

<div class="full-width-table">

| Provider | Documentation |
|----------|---------------|
| Anthropic | [Prism Anthropic](https://prismphp.com/providers/anthropic.html) |
| DeepSeek | [Prism DeepSeek](https://prismphp.com/providers/deepseek.html) |
| ElevenLabs | [Prism ElevenLabs](https://prismphp.com/providers/elevenlabs.html) |
| Gemini | [Prism Gemini](https://prismphp.com/providers/gemini.html) |
| Groq | [Prism Groq](https://prismphp.com/providers/groq.html) |
| Mistral | [Prism Mistral](https://prismphp.com/providers/mistral.html) |
| Ollama | [Prism Ollama](https://prismphp.com/providers/ollama.html) |
| OpenAI | [Prism OpenAI](https://prismphp.com/providers/openai.html) |
| OpenRouter | [Prism OpenRouter](https://prismphp.com/providers/openrouter.html) |
| Voyage AI | [Prism Voyage AI](https://prismphp.com/providers/voyageai.html) |
| xAI | [Prism xAI](https://prismphp.com/providers/xai.html) |

</div>

## Provider Support

Not all providers support all features. Check the [Prism Provider Support Matrix](https://prismphp.com/getting-started/introduction.html#provider-support) for detailed compatibility.

::: warning Model Dependent
Support may be model dependent. Check with your provider for model-specific features and support.
:::

## Using Providers

### In Agent Definitions

```php
use Atlasphp\Atlas\Agents\AgentDefinition;

class MyAgent extends AgentDefinition
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

### Runtime Override

```php
use Atlasphp\Atlas\Atlas;

$response = Atlas::agent('my-agent')
    ->withProvider('openai', 'gpt-4o')
    ->chat('Hello');
```

### Direct Prism Usage

```php
$response = Atlas::text()
    ->using('anthropic', 'claude-sonnet-4-20250514')
    ->withPrompt('Hello')
    ->asText();
```

## Configuration

Configure provider API keys in your `.env` file:

```env
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...
```

See [Configuration](/getting-started/configuration) for full configuration options.

## API Reference

```php
// Agent provider configuration (override in agent class)
public function provider(): ?string;    // e.g., 'openai', 'anthropic', 'gemini'
public function model(): ?string;       // e.g., 'gpt-4o', 'claude-sonnet-4-20250514'

// Runtime provider override
Atlas::agent('agent')
    ->withProvider(string $provider, ?string $model = null)
    ->chat(string $input);

// Direct Prism usage
Atlas::text()->using(string $provider, string $model);
Atlas::image()->using(string $provider, string $model);
Atlas::audio()->using(string $provider, string $model);
Atlas::embeddings()->using(string $provider, string $model);
Atlas::moderation()->using(string $provider, string $model);

// Common provider/model combinations
->using('openai', 'gpt-4o')
->using('openai', 'gpt-4o-mini')
->using('anthropic', 'claude-sonnet-4-20250514')
->using('anthropic', 'claude-opus-4-20250514')
->using('gemini', 'gemini-2.0-flash')
->using('mistral', 'mistral-large-latest')
->using('groq', 'llama-3.3-70b-versatile')
->using('deepseek', 'deepseek-chat')
->using('ollama', 'llama3.2')
```

## Next Steps

- [Configuration](/getting-started/configuration) — Configure providers and defaults
- [Agents](/core-concepts/agents) — Create agents with specific providers
- [Custom Providers](/advanced/custom-providers) — Register custom providers
