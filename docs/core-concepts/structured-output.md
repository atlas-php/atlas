# Structured Output

Atlas supports schema-based responses for extracting structured data from AI responses.

## What is Structured Output?

Instead of free-form text, structured output returns data in a predefined format:

```php
use Atlasphp\Atlas\Schema\Schema;

$schema = Schema::object('sentiment', 'Sentiment analysis result')
    ->enum('sentiment', 'The sentiment', ['positive', 'negative', 'neutral'])
    ->number('confidence', 'Confidence score from 0 to 1')
    ->build();

$response = Atlas::agent('analyzer')->withSchema($schema)->chat('I love this product!');

echo $response->structured['sentiment'];   // "positive"
echo $response->structured['confidence'];  // 0.95
```

## Schema Builder

The Schema Builder provides a fluent API for defining schemas with automatic required field tracking.

### Basic Usage

```php
use Atlasphp\Atlas\Schema\Schema;

$schema = Schema::object('contact', 'Contact information')
    ->string('name', 'Full name')
    ->string('email', 'Email address')
    ->number('age', 'Age in years')
    ->build();
```

All fields are **required by default**, matching OpenAI's recommended practice.

### Property Types

#### String

```php
->string('name', 'The person\'s full name')
```

#### Number

```php
->number('score', 'A score between 0 and 100')
```

#### Integer

```php
->integer('count', 'Number of items')
```

#### Boolean

```php
->boolean('is_valid', 'Whether the input is valid')
```

#### Enum

```php
->enum('status', 'The current status', ['pending', 'approved', 'rejected'])
```

### Arrays

#### String Array

```php
->stringArray('tags', 'List of relevant tags')
```

#### Number Array

```php
->numberArray('scores', 'List of scores')
```

#### Object Array

```php
->array('items', 'Order items', fn($s) => $s
    ->string('name', 'Item name')
    ->number('quantity', 'Quantity')
    ->number('price', 'Unit price')
)
```

### Nested Objects

```php
->object('address', 'Mailing address', fn($s) => $s
    ->string('street', 'Street address')
    ->string('city', 'City name')
    ->string('zip', 'ZIP or postal code')
)
```

### Optional Fields

Mark fields as optional with `->optional()`:

```php
$schema = Schema::object('user', 'User profile')
    ->string('name', 'Full name')           // required
    ->string('email', 'Email address')      // required
    ->string('phone', 'Phone number')->optional()  // NOT required
    ->build();
```

### Nullable Fields

Mark fields as nullable with `->nullable()` (implies optional):

```php
$schema = Schema::object('record', 'Data record')
    ->string('id', 'Record ID')
    ->string('notes', 'Optional notes')->nullable()
    ->build();
```

## Complex Examples

### Nested Schema with Optional Fields

```php
$schema = Schema::object('order', 'Order details')
    ->string('id', 'Order ID')
    ->object('customer', 'Customer info', fn($s) => $s
        ->string('name', 'Customer name')
        ->string('email', 'Email address')->optional()
    )
    ->array('items', 'Order items', fn($s) => $s
        ->string('name', 'Item name')
        ->number('quantity', 'Quantity')
        ->number('price', 'Unit price')->optional()
    )
    ->build();
```

### Classification Schema

```php
$schema = Schema::object('classification', 'Content classification')
    ->enum('category', 'Content category', ['support', 'sales', 'feedback', 'other'])
    ->number('confidence', 'Classification confidence')
    ->stringArray('tags', 'Relevant tags')->optional()
    ->build();

$response = Atlas::agent('classifier')
    ->withSchema($schema)
    ->chat('I want to return my order and get a refund');

// $response->structured = ['category' => 'support', 'confidence' => 0.92, 'tags' => ['refund', 'return']]
```

### Data Extraction Schema

```php
$schema = Schema::object('contact', 'Extracted contact')
    ->string('name', 'Full name')
    ->string('email', 'Email address')->optional()
    ->string('phone', 'Phone number')->optional()
    ->build();

$response = Atlas::agent('extractor')
    ->withSchema($schema)
    ->chat('Contact: John Smith, john@example.com, 555-1234');

// $response->structured = ['name' => 'John Smith', 'email' => 'john@example.com', 'phone' => '555-1234']
```

### Summary Schema

```php
$schema = Schema::object('summary', 'Article summary')
    ->string('title', 'Suggested title')
    ->string('summary', 'Brief summary (2-3 sentences)')
    ->stringArray('key_points', 'Key takeaways')
    ->enum('sentiment', 'Overall sentiment', ['positive', 'negative', 'neutral'])
    ->build();
```

## With Variables and Messages

```php
$response = Atlas::agent('extractor')
    ->withMessages($messages)
    ->withVariables(['user_name' => 'John'])
    ->withSchema($schema)
    ->chat('Extract the data');

$data = $response->structured;
```

## Checking Responses

```php
$response = Atlas::agent('agent')->withSchema($schema)->chat('Analyze this');

if ($response->hasStructured()) {
    $data = $response->structured;
    // Process structured data
} else {
    // Handle missing structured data
    $text = $response->text;
}
```

## Best Practices

### 1. Provide Clear Descriptions

```php
// Good - specific descriptions
->string('email', 'A valid email address in format user@domain.com')

// Less helpful
->string('email', 'Email')
```

### 2. Use Required Fields Appropriately

Only mark fields as optional if they're truly optional:

```php
Schema::object('user', 'User profile')
    ->string('name', 'Full name')         // Essential
    ->string('email', 'Email address')    // Essential
    ->string('phone', 'Phone number')->optional()  // Nice to have
    ->build();
```

### 3. Match Schema to Agent Purpose

Design your agent's system prompt to produce the expected structure:

```php
class DataExtractorAgent extends AgentDefinition
{
    public function systemPrompt(): string
    {
        return <<<PROMPT
        You extract structured data from text.
        Focus on finding the specific fields requested.
        If a field cannot be determined, omit it from the response.
        PROMPT;
    }
}
```

## Next Steps

- [Chat](/capabilities/chat) — Chat API details
- [Agents](/core-concepts/agents) — Agent configuration
- [Response Objects](/api-reference/response-objects) — AgentResponse API
