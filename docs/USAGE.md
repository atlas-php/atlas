# Atlas Usage Guide

> Primary usage patterns for the Atlas facade.

---

## Chat

Execute conversations with AI agents.

### Simple Chat

```php
use Atlasphp\Atlas\Providers\Facades\Atlas;

$response = Atlas::agent('support-agent')->chat('Hello, I need help with my order');

echo $response->text;
// "Hi! I'd be happy to help with your order. Could you provide your order number?"
```

### Chat with Agent Class

```php
use App\Agents\SupportAgent;

// By class name
$response = Atlas::agent(SupportAgent::class)->chat('What is your return policy?');

// By instance
$response = Atlas::agent(new SupportAgent())->chat('What is your return policy?');
```

### Chat with Conversation History

```php
$messages = [
    ['role' => 'user', 'content' => 'My order number is 12345'],
    ['role' => 'assistant', 'content' => 'I found your order. How can I help?'],
];

$response = Atlas::agent('support-agent')->withMessages($messages)->chat('Where is my package?');
```

### Chat with Structured Output

```php
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Schema\NumberSchema;

$schema = new ObjectSchema(
    name: 'sentiment',
    description: 'Sentiment analysis result',
    properties: [
        new StringSchema('sentiment', 'positive, negative, or neutral'),
        new NumberSchema('confidence', 'Confidence score 0-1'),
    ],
    requiredFields: ['sentiment', 'confidence'],
);

$response = Atlas::agent('analyzer')->withSchema($schema)->chat('I love this product!');

echo $response->structured['sentiment'];    // "positive"
echo $response->structured['confidence'];   // 0.95
```

---

## Multi-Turn Conversations

Build context fluently using the agent builder.

### With Variables

Variables interpolate into the agent's system prompt:

```php
$response = Atlas::agent('support-agent')
    ->withMessages($messages)
    ->withVariables([
        'user_name' => 'Alice',
        'account_tier' => 'premium',
        'app_name' => 'Acme Inc',
    ])
    ->chat('What features do I have access to?');
```

### With Metadata

Metadata is passed to pipeline middleware and tools:

```php
$response = Atlas::agent('support-agent')
    ->withMessages($messages)
    ->withVariables(['user_name' => 'Alice'])
    ->withMetadata([
        'user_id' => 123,
        'session_id' => 'abc-456',
        'tenant_id' => 'acme',
    ])
    ->chat('Check my recent orders');
```

### Complete Example

```php
// Load conversation from database
$conversation = Conversation::find($id);

$response = Atlas::agent('support-agent')
    ->withMessages($conversation->messages)
    ->withVariables([
        'user_name' => $user->name,
        'account_tier' => $user->subscription->tier,
    ])
    ->withMetadata([
        'user_id' => $user->id,
        'conversation_id' => $conversation->id,
    ])
    ->chat($request->input('message'));

// Save updated conversation
$conversation->messages = array_merge($conversation->messages, [
    ['role' => 'user', 'content' => $request->input('message')],
    ['role' => 'assistant', 'content' => $response->text],
]);
$conversation->save();

return response()->json([
    'message' => $response->text,
    'tokens' => $response->totalTokens(),
]);
```

---

## Embeddings

Generate vector embeddings for semantic search, RAG, and similarity matching.

### Single Embedding

```php
$embedding = Atlas::embeddings()->generate('What is the return policy?');
// Returns array of 1536 floats (for text-embedding-3-small)

// Use for similarity search
$similarDocs = Document::query()
    ->orderByRaw('embedding <-> ?', [json_encode($embedding)])
    ->limit(5)
    ->get();
```

### Batch Embeddings

```php
$texts = [
    'How do I return an item?',
    'What is your shipping policy?',
    'Do you offer refunds?',
];

$embeddings = Atlas::embeddings()->generate($texts);
// Returns array of 3 embedding vectors
```

### Get Embedding Dimensions

```php
$dimensions = Atlas::embeddings()->dimensions();
// 1536 (for text-embedding-3-small)
```

---

## Image Generation

Generate images using DALL-E and other providers.

### Basic Image Generation

```php
$result = Atlas::image()->generate('A sunset over mountains');

echo $result['url'];           // URL to generated image
echo $result['revised_prompt']; // AI-revised prompt (if applicable)
```

### With Provider and Model

```php
$result = Atlas::image('openai', 'dall-e-3')
    ->generate('A futuristic cityscape');
```

### With Options

```php
$result = Atlas::image('openai', 'dall-e-3')
    ->size('1024x1024')
    ->quality('hd')
    ->generate('A photorealistic portrait of a robot');

// Save the image
$imageContent = file_get_contents($result['url']);
Storage::put('images/robot.png', $imageContent);
```

### With Provider-Specific Options

```php
$result = Atlas::image()
    ->withProvider('openai', 'dall-e-3')
    ->size('1024x1024')
    ->quality('hd')
    ->withProviderOptions(['style' => 'vivid'])  // OpenAI: 'vivid' or 'natural'
    ->generate('A photorealistic portrait');
```

### Fluent Configuration

```php
$result = Atlas::image()
    ->withProvider('openai', 'dall-e-3')
    ->size('1792x1024')
    ->quality('standard')
    ->generate('An abstract painting of emotions');
```

---

## Audio

Text-to-speech and speech-to-text capabilities.

### Text to Speech

```php
$result = Atlas::speech()->generate('Hello, welcome to our service!');

// Save audio file
file_put_contents('welcome.mp3', base64_decode($result['audio']));
```

### With Voice Selection

```php
$result = Atlas::speech('openai', 'tts-1')
    ->voice('nova')
    ->format('mp3')
    ->generate('Thank you for calling. How can I help you today?');
```

### With Speed Control

```php
$result = Atlas::speech('openai', 'tts-1')
    ->voice('nova')
    ->speed(1.25)  // 0.25 to 4.0 for OpenAI
    ->generate('This is faster speech.');
```

### HD Quality Speech

```php
$result = Atlas::speech('openai', 'tts-1-hd')
    ->voice('alloy')
    ->generate('This is high-definition audio quality.');
```

### With Provider-Specific Options

```php
$result = Atlas::speech()
    ->voice('nova')
    ->speed(1.0)
    ->withProviderOptions(['language' => 'en'])
    ->generate('Hello world!');
```

### Speech to Text (Transcription)

```php
$result = Atlas::speech()->transcribe('/path/to/audio.mp3');

echo $result['text'];      // Transcribed text
echo $result['language'];  // Detected language (e.g., 'en')
echo $result['duration'];  // Audio duration in seconds
```

### With Specific Transcription Model

```php
$result = Atlas::speech()
    ->transcriptionModel('whisper-1')
    ->transcribe($uploadedFile->path());
```

### Transcription with Options

```php
$result = Atlas::speech()
    ->transcriptionModel('whisper-1')
    ->withProviderOptions([
        'language' => 'en',
        'prompt' => 'Technical terminology context',
    ])
    ->transcribe($uploadedFile->path());
```

---

## Response Handling

All chat operations return an `AgentResponse` object.

### Text Response

```php
$response = Atlas::agent('agent')->chat('Hello');

if ($response->hasText()) {
    echo $response->text;
}
```

### Structured Response

```php
$response = Atlas::agent('agent')->withSchema($schema)->chat('Extract data');

if ($response->hasStructured()) {
    $data = $response->structured;
    // Access as array: $data['field']
}
```

### Tool Calls

```php
if ($response->hasToolCalls()) {
    foreach ($response->toolCalls as $call) {
        echo $call['name'];       // Tool name
        print_r($call['arguments']); // Tool arguments
    }
}
```

### Token Usage

```php
$response = Atlas::agent('agent')->chat('Hello');

echo $response->totalTokens();      // Total tokens used
echo $response->promptTokens();     // Tokens in prompt
echo $response->completionTokens(); // Tokens in response
```

### Metadata

```php
// Access response metadata
$value = $response->get('key', 'default');

// Check usage
if ($response->hasUsage()) {
    $usage = $response->usage;
}
```

---

## Quick Reference

| Method | Description |
|--------|-------------|
| `Atlas::agent($agent)->chat($input)` | Simple chat with agent |
| `Atlas::agent($agent)->withMessages($messages)->chat($input)` | Chat with history |
| `Atlas::agent($agent)->withVariables($vars)->chat($input)` | Chat with variables |
| `Atlas::agent($agent)->withSchema($schema)->chat($input)` | Structured output |
| `Atlas::agent($agent)->withToolChoice($choice)->chat($input)` | Control tool usage |
| `Atlas::embeddings()->generate($text)` | Single text embedding |
| `Atlas::embeddings()->generate($texts)` | Batch embeddings (array input) |
| `Atlas::embeddings()->dimensions()` | Get vector dimensions |
| `Atlas::image()` | Image generation service |
| `Atlas::image($provider, $model)` | Image with specific config |
| `Atlas::speech()` | Speech service |
| `Atlas::speech($provider, $model)` | Speech with specific config |
| `Atlas::fake()` | Enable test mode (no API calls) |
| `Atlas::unfake()` | Restore normal operation |

### Service Method Chaining

| Service | Method | Description |
|---------|--------|-------------|
| Image | `->size($size)` | Set image dimensions (e.g., '1024x1024') |
| Image | `->quality($quality)` | Set quality ('standard', 'hd') |
| Image | `->withProviderOptions($options)` | Set provider-specific options |
| Audio | `->voice($voice)` | Set TTS voice |
| Audio | `->speed($speed)` | Set speech speed (0.25-4.0) |
| Audio | `->format($format)` | Set audio format |
| Audio | `->withProviderOptions($options)` | Set provider-specific options |
