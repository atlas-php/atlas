<p align="center">
  <img src="https://img.shields.io/badge/php-8.4%2B-blue?style=flat-square" alt="PHP Version">
  <img src="https://img.shields.io/badge/laravel-12.x-orange?style=flat-square" alt="Laravel">
  <a href="https://github.com/atlas-php/atlas"><img src="https://github.com/atlas-php/atlas/workflows/Build/badge.svg" alt="Build Status"></a>
  <img src="https://img.shields.io/badge/license-MIT-green?style=flat-square" alt="License">
</p>

# Atlas

**The production-grade AI agent framework for Laravel.** Built on [Prism PHP](https://github.com/prism-php/prism).

```php
$response = Atlas::chat('support-agent', 'I need help with my order');
```

---

## Why Atlas?

Atlas gives you the infrastructure to build real applications; Prism handles the LLM communications.

- **Agents** — Define many agents. Register once, use anywhere.
- **Tools** — Give agents access to abilities directly to your services.
- **System Prompts** — Variables like `{user_name}` interpolate at runtime.
- **Pipelines** — Hook into any stage for logging, metrics, or auth.
- **Multi-Provider** — OpenAI, Anthropic, others. Switch with config.
- **Beyond Chat** — Embeddings, images, and speech through one facade.

### Note from the Author
> _I've been building Atlas through many iterations over the past year. This version (RC4) is stable, battle-tested, and already powering large-scale production applications. The roadmap includes Nexus; a companion package that will handle persistence and AI orchestration, complementing Atlas's stateless design. If you have any suggestions feel free to submit an issue. -TM_
---

## Table of Contents

- [Installation](#installation)
- [Agents](#agents)
- [Tools](#tools)
- [Conversations](#conversations)
- [Embeddings](#embeddings)
- [Images](#images)
- [Speech](#speech)
- [Pipelines](#pipelines)
- [Configuration](#configuration)
- [Testing](#testing)
- [Documentation](#documentation)

---

## Installation

```bash
composer require atlas-php/atlas
php artisan vendor:publish --tag=atlas-config
```

```env
OPENAI_API_KEY=sk-...
```

---

## Agents

Define an agent with its provider, model, prompt, and tools:

```php
use Atlasphp\Atlas\Agents\AgentDefinition;

class SupportAgent extends AgentDefinition
{
    public function provider(): string { return 'openai'; }
    public function model(): string { return 'gpt-4o'; }

    public function systemPrompt(): string
    {
        return 'You are a support agent for {company}. Customer: {customer_name}.';
    }

    public function tools(): array
    {
        return [
            LookupOrderTool::class, 
            RefundTool::class
        ];
    }
}
```

Register in a service provider:

```php
app(AgentRegistryContract::class)->register(SupportAgent::class);
```

Use by key, class, or instance:

```php
Atlas::chat('support', 'Hello');
Atlas::chat(SupportAgent::class, 'Hello');
Atlas::chat(new SupportAgent(), 'Hello');
```

---

## Tools

Give agents abilities with typed parameters:

```php
use Atlasphp\Atlas\Tools\ToolDefinition;
use Atlasphp\Atlas\Tools\Support\{ToolParameter, ToolResult, ToolContext};

class LookupOrderTool extends ToolDefinition
{
    public function name(): string { return 'lookup_order'; }
    public function description(): string { return 'Look up order by ID'; }

    public function parameters(): array
    {
        return [
            ToolParameter::string('order_id', 'The order ID', required: true),
        ];
    }

    public function handle(array $args, ToolContext $context): ToolResult
    {
        $order = Order::find($args['order_id']);
        
        return $order 
            ? ToolResult::json($order->toArray())
            : ToolResult::error('Order not found');
    }
}
```

Register tools:

```php
app(ToolRegistryContract::class)->register(LookupOrderTool::class);
```

Pass context to tools:

```php
$response = Atlas::forMessages($messages)
    ->withMetadata(['user_id' => $user->id])
    ->chat('support', $input);

// In tool: $context->getMeta('user_id')
```

Parameter types: `string`, `integer`, `number`, `boolean`, `enum`, `array`, `object`.

---

## Conversations

Atlas is stateless. You manage history:

```php
$messages = [
    ['role' => 'user', 'content' => 'Order 12345'],
    ['role' => 'assistant', 'content' => 'Found it. How can I help?'],
];

$response = Atlas::forMessages($messages)
    ->withVariables(['customer_name' => 'Alice', 'company' => 'Acme'])
    ->chat('support', 'Where is my package?');
```

For structured output:

```php
$schema = new ObjectSchema(
    name: 'sentiment',
    properties: [
        new StringSchema('sentiment', 'positive, negative, neutral'),
        new NumberSchema('confidence', '0-1'),
    ],
    requiredFields: ['sentiment', 'confidence'],
);

$response = Atlas::chat('analyzer', 'I love this!', schema: $schema);
echo $response->structured['sentiment']; // "positive"
```

---

## Embeddings

```php
$vector = Atlas::embed('Hello world');              // Single
$vectors = Atlas::embedBatch(['Text 1', 'Text 2']); // Batch
$dims = Atlas::embeddingDimensions();               // 1536
```

---

## Images

```php
$result = Atlas::image('openai', 'dall-e-3')
    ->size('1024x1024')
    ->generate('A sunset over mountains');

echo $result['url'];
```

---

## Speech

```php
// Text to speech
$result = Atlas::speech('openai', 'tts-1')
    ->voice('nova')
    ->speak('Hello world');

file_put_contents('audio.mp3', base64_decode($result['audio']));

// Speech to text
$result = Atlas::speech()->transcribe('/path/to/audio.mp3');
echo $result['text'];
```

---

## Pipelines

Hook into execution for logging, metrics, or custom logic:

```php
use Atlasphp\Atlas\Foundation\Contracts\PipelineContract;

class LoggingMiddleware implements PipelineContract
{
    public function handle(mixed $data, Closure $next): mixed
    {
        logger()->info('Executing', ['agent' => $data['agent']->key()]);
        return $next($data);
    }
}

app(PipelineRegistry::class)->register('agent.before_execute', LoggingMiddleware::class);
```

Available hooks: `agent.before_execute`, `agent.after_execute`, `tool.before_execute`, `tool.after_execute`, `embedding.before_generate`, `image.before_generate`, and more.

---

## Configuration

```php
// config/atlas.php
return [
    'providers' => [
        'openai' => ['api_key' => env('OPENAI_API_KEY')],
        'anthropic' => ['api_key' => env('ANTHROPIC_API_KEY')],
    ],
    'chat' => ['provider' => 'openai', 'model' => 'gpt-4o'],
    'embedding' => ['provider' => 'openai', 'model' => 'text-embedding-3-small'],
];
```

---

## Testing

```php
Atlas::fake();

Atlas::shouldReceive('chat')
    ->with('support', 'Hello')
    ->andReturn(new AgentResponse(['text' => 'Hi!']));
```

```bash
composer test     # Tests
composer check    # All checks
```

---

## Documentation

| Guide                                                               | Description                 |
|---------------------------------------------------------------------|-----------------------------|
| [Installation](docs/guides/Installation.md)                         | Setup walkthrough           |
| [Creating Agents](docs/guides/Creating-Agents.md)                   | Agent configuration         |
| [Creating Tools](docs/guides/Creating-Tools.md)                     | Tool parameters and results |
| [Multi-Turn Conversations](docs/guides/Multi-Turn-Conversations.md) | Conversation handling       |
| [Extending Atlas](docs/guides/Extending-Atlas.md)                   | Pipeline middleware         |

Specs: [docs/spec/](docs/spec/)

---

## Requirements

- PHP 8.4+
- Laravel 12.x

## License

MIT