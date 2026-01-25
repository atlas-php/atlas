# Structured Output

Atlas supports schema-based responses for extracting structured data from AI responses.

::: tip Prism Reference
Atlas uses Prism's schema system under the hood. For detailed schema documentation, see [Prism Structured Output](https://prismphp.com/core-concepts/structured-output.html) and [Prism Schemas](https://prismphp.com/core-concepts/schemas.html).
:::

## What is Structured Output?

Instead of free-form text, structured output returns data in a predefined format:

```php
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Schema\Schema;

$response = Atlas::agent('analyzer')
    ->withSchema(
        Schema::object('sentiment', 'Sentiment analysis result')
            ->enum('sentiment', 'The sentiment', ['positive', 'negative', 'neutral'])
            ->number('confidence', 'Confidence score from 0 to 1')
    )
    ->chat('I love this product!');

echo $response->structured['sentiment'];   // "positive"
echo $response->structured['confidence'];  // 0.95
```

## Atlas Schema Builder

Atlas provides a fluent Schema Builder that creates Prism schemas with less boilerplate and automatic required field tracking.

### Basic Usage

```php
use Atlasphp\Atlas\Schema\Schema;

// Inline - auto-builds when passed to withSchema()
$response = Atlas::agent('extractor')
    ->withSchema(
        Schema::object('contact', 'Contact information')
            ->string('name', 'Full name')
            ->string('email', 'Email address')
            ->number('age', 'Age in years')
    )
    ->chat('Extract: John Smith, john@example.com, 30 years old');

// Or build separately for reuse
$schema = Schema::object('contact', 'Contact information')
    ->string('name', 'Full name')
    ->string('email', 'Email address')
    ->build();
```

The Schema Builder automatically tracks required fields and builds valid Prism schema objects. All fields are **required by default**, matching OpenAI's recommended practice.

### Property Types

```php
// String
->string('name', 'The person\'s full name')

// Number (float/decimal)
->number('score', 'A score between 0 and 100')

// Integer (alias for number)
->integer('count', 'Number of items')

// Boolean
->boolean('is_valid', 'Whether the input is valid')

// Enum
->enum('status', 'The current status', ['pending', 'approved', 'rejected'])

// String array
->stringArray('tags', 'List of relevant tags')

// Number array
->numberArray('scores', 'List of scores')

// Object array
->array('items', 'Order items', fn($s) => $s
    ->string('name', 'Item name')
    ->number('quantity', 'Quantity')
)

// Nested object
->object('address', 'Mailing address', fn($s) => $s
    ->string('street', 'Street address')
    ->string('city', 'City name')
)
```

### Optional and Nullable Fields

```php
Schema::object('user', 'User profile')
    ->string('name', 'Full name')                        // required
    ->string('email', 'Email address')                   // required
    ->string('phone', 'Phone number')->optional()        // NOT required
    ->string('notes', 'Optional notes')->nullable()      // NOT required + can be null
    ->build()
```

- `->optional()` — Removes the field from required fields
- `->nullable()` — Makes the field nullable (implies optional)

## Using Prism Schemas Directly

Atlas accepts Prism schema objects directly. If you're already familiar with Prism or need advanced schema features, you can use Prism's schema classes:

```php
use Atlasphp\Atlas\Atlas;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\EnumSchema;

$schema = new ObjectSchema(
    name: 'sentiment',
    description: 'Sentiment analysis result',
    properties: [
        new EnumSchema('sentiment', 'The sentiment', ['positive', 'negative', 'neutral']),
        new NumberSchema('confidence', 'Confidence score from 0 to 1'),
    ],
    requiredFields: ['sentiment', 'confidence'],
);

$response = Atlas::agent('analyzer')
    ->withSchema($schema)
    ->chat('I love this product!');
```

Available Prism schema types:
- `StringSchema` — Text values
- `NumberSchema` — Integers and floats
- `BooleanSchema` — True/false values
- `ArraySchema` — Lists of items
- `EnumSchema` — Predefined set of options
- `ObjectSchema` — Nested structures

## Structured Output Modes

By default, Atlas uses the provider's native structured output mode. For OpenAI, this requires **all fields to be required**. If you need optional fields, use JSON mode.

### JSON Mode (for Optional Fields)

Use `->usingJsonMode()` when your schema has optional fields:

```php
$response = Atlas::agent('extractor')
    ->withSchema(
        Schema::object('contact', 'Contact info')
            ->string('name', 'Full name')
            ->string('email', 'Email')
            ->string('phone', 'Phone number')->optional()
    )
    ->usingJsonMode()  // Required for optional fields with OpenAI
    ->chat('Extract: John at john@example.com');
```

### Available Modes

```php
// Let Atlas choose the best mode (default)
->usingAutoMode()

// Use native JSON schema (faster, but all fields must be required for OpenAI)
->usingNativeMode()

// Use JSON mode (allows optional fields, works with all providers)
->usingJsonMode()
```

**When to use JSON mode:**
- Your schema has `->optional()` fields
- You need flexibility in what the model returns
- You're working with providers that don't support native structured output

## Examples

### Example: Nested Schema with Optional Fields

```php
$response = Atlas::agent('order-extractor')
    ->withSchema(
        Schema::object('order', 'Order details')
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
    )
    ->usingJsonMode()
    ->chat($orderText);
```

### Example: Classification Schema

```php
$response = Atlas::agent('classifier')
    ->withSchema(
        Schema::object('classification', 'Content classification')
            ->enum('category', 'Content category', ['support', 'sales', 'feedback', 'other'])
            ->number('confidence', 'Classification confidence')
            ->stringArray('tags', 'Relevant tags')->optional()
    )
    ->usingJsonMode()
    ->chat('I want to return my order and get a refund');

// $response->structured = ['category' => 'support', 'confidence' => 0.92, 'tags' => ['refund', 'return']]
```

### Example: Data Extraction Schema

```php
$response = Atlas::agent('extractor')
    ->withSchema(
        Schema::object('contact', 'Extracted contact')
            ->string('name', 'Full name')
            ->string('email', 'Email address')->optional()
            ->string('phone', 'Phone number')->optional()
    )
    ->usingJsonMode()
    ->chat('Contact: John Smith, john@example.com, 555-1234');

// $response->structured = ['name' => 'John Smith', 'email' => 'john@example.com', 'phone' => '555-1234']
```

### Example: Summary Schema

```php
$response = Atlas::agent('summarizer')
    ->withSchema(
        Schema::object('summary', 'Article summary')
            ->string('title', 'Suggested title')
            ->string('summary', 'Brief summary (2-3 sentences)')
            ->stringArray('key_points', 'Key takeaways')
            ->enum('sentiment', 'Overall sentiment', ['positive', 'negative', 'neutral'])
    )
    ->chat($articleText);
```

## With Variables and Messages

```php
$response = Atlas::agent('extractor')
    ->withMessages($messages)
    ->withVariables(['user_name' => 'John'])
    ->withSchema(
        Schema::object('data', 'Extracted data')
            ->string('field', 'The field')
    )
    ->chat('Extract the data');

$data = $response->structured;
```

## Checking Responses

```php
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Schema\Schema;

$response = Atlas::agent('extractor')
    ->withSchema(
        Schema::object('person', 'Person details')
            ->string('name', 'Full name')
            ->string('email', 'Email address')
    )
    ->chat('Extract from: John Smith can be reached at john@example.com');

if ($response->structured !== null) {
    $person = $response->structured;
    echo "Name: {$person['name']}";    // "John Smith"
    echo "Email: {$person['email']}";  // "john@example.com"
} else {
    // Handle case where extraction failed
    echo "Could not extract person data";
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
```

### 3. Match Schema to Agent Purpose

Design your agent's system prompt to produce the expected structure:

```php
use Atlasphp\Atlas\Agents\AgentDefinition;

class DataExtractorAgent extends AgentDefinition
{
    public function systemPrompt(): ?string
    {
        return <<<PROMPT
        You extract structured data from text.
        Focus on finding the specific fields requested.
        If a field cannot be determined, omit it from the response.
        PROMPT;
    }
}
```

## API Reference

```php
// Schema factory
Schema::object(string $name, string $description): SchemaBuilder;

// SchemaBuilder property methods (all return SchemaProperty for chaining)
$builder->string(string $name, string $description): SchemaProperty;
$builder->number(string $name, string $description): SchemaProperty;
$builder->integer(string $name, string $description): SchemaProperty;
$builder->boolean(string $name, string $description): SchemaProperty;
$builder->enum(string $name, string $description, array $options): SchemaProperty;
$builder->stringArray(string $name, string $description): SchemaProperty;
$builder->numberArray(string $name, string $description): SchemaProperty;
$builder->object(string $name, string $description, callable $callback): SchemaProperty;
$builder->array(string $name, string $description, callable $callback): SchemaProperty;
$builder->build(): ObjectSchema;

// SchemaProperty modifiers
$property->optional(): SchemaProperty;  // Remove from required fields
$property->nullable(): SchemaProperty;  // Allow null values (implies optional)

// Agent executor schema methods
Atlas::agent('agent')
    ->withSchema(SchemaBuilder|ObjectSchema $schema)
    ->usingAutoMode()    // Let Atlas choose (default)
    ->usingNativeMode()  // Use native JSON schema
    ->usingJsonMode()    // Use JSON mode (required for optional fields)
    ->chat(string $input);

// Response
$response->structured;  // array|null - The structured data
```

## Next Steps

- [Chat](/capabilities/chat) — Chat API details
- [Agents](/core-concepts/agents) — Agent configuration
- [Prism Schemas](https://prismphp.com/core-concepts/schemas.html) — Full schema reference
