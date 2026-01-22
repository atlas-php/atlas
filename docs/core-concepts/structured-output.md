# Structured Output

Atlas supports schema-based responses for extracting structured data from AI responses.

## What is Structured Output?

Instead of free-form text, structured output returns data in a predefined format:

```php
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Schema\NumberSchema;

$schema = new ObjectSchema(
    name: 'sentiment',
    description: 'Sentiment analysis result',
    properties: [
        new StringSchema('sentiment', 'The sentiment: positive, negative, or neutral'),
        new NumberSchema('confidence', 'Confidence score from 0 to 1'),
    ],
    requiredFields: ['sentiment', 'confidence'],
);

$response = Atlas::chat('analyzer', 'I love this product!', schema: $schema);

echo $response->structured['sentiment'];   // "positive"
echo $response->structured['confidence'];  // 0.95
```

## Schema Types

Atlas uses Prism's schema classes for type definitions.

### String Schema

```php
use Prism\Prism\Schema\StringSchema;

new StringSchema('name', 'The person\'s full name');
```

### Number Schema

```php
use Prism\Prism\Schema\NumberSchema;

new NumberSchema('score', 'A score between 0 and 100');
```

### Boolean Schema

```php
use Prism\Prism\Schema\BooleanSchema;

new BooleanSchema('is_valid', 'Whether the input is valid');
```

### Enum Schema

```php
use Prism\Prism\Schema\EnumSchema;

new EnumSchema('status', 'The current status', ['pending', 'approved', 'rejected']);
```

### Array Schema

```php
use Prism\Prism\Schema\ArraySchema;

new ArraySchema(
    'tags',
    'List of relevant tags',
    new StringSchema('tag', 'A single tag')
);
```

### Object Schema

```php
use Prism\Prism\Schema\ObjectSchema;

new ObjectSchema(
    name: 'address',
    description: 'A mailing address',
    properties: [
        new StringSchema('street', 'Street address'),
        new StringSchema('city', 'City name'),
        new StringSchema('zip', 'ZIP or postal code'),
        new StringSchema('country', 'Country code'),
    ],
    requiredFields: ['street', 'city', 'country'],
);
```

## Nested Schemas

Create complex nested structures:

```php
$schema = new ObjectSchema(
    name: 'order_extraction',
    description: 'Extracted order information',
    properties: [
        new StringSchema('order_id', 'The order identifier'),
        new ObjectSchema(
            name: 'customer',
            description: 'Customer information',
            properties: [
                new StringSchema('name', 'Customer name'),
                new StringSchema('email', 'Customer email'),
            ],
            requiredFields: ['name'],
        ),
        new ArraySchema(
            'items',
            'Ordered items',
            new ObjectSchema(
                name: 'item',
                description: 'A single item',
                properties: [
                    new StringSchema('name', 'Item name'),
                    new NumberSchema('quantity', 'Quantity ordered'),
                    new NumberSchema('price', 'Unit price'),
                ],
                requiredFields: ['name', 'quantity'],
            ),
        ),
    ],
    requiredFields: ['order_id', 'items'],
);
```

## Using with forMessages()

```php
$response = Atlas::forMessages($messages)
    ->withVariables(['user_name' => 'John'])
    ->chat('extractor', 'Extract the data', $schema);

$data = $response->structured;
```

## Checking Responses

```php
$response = Atlas::chat('agent', 'Analyze this', schema: $schema);

if ($response->hasStructured()) {
    $data = $response->structured;
    // Process structured data
} else {
    // Handle missing structured data
    $text = $response->text;
}
```

## Use Cases

### Data Extraction

Extract structured data from unstructured text:

```php
$schema = new ObjectSchema(
    name: 'contact',
    description: 'Contact information',
    properties: [
        new StringSchema('name', 'Full name'),
        new StringSchema('email', 'Email address'),
        new StringSchema('phone', 'Phone number'),
    ],
    requiredFields: ['name'],
);

$response = Atlas::chat(
    'extractor',
    'Contact: John Smith, john@example.com, 555-1234',
    schema: $schema,
);

// $response->structured = ['name' => 'John Smith', 'email' => 'john@example.com', 'phone' => '555-1234']
```

### Classification

Classify content into categories:

```php
$schema = new ObjectSchema(
    name: 'classification',
    description: 'Content classification',
    properties: [
        new EnumSchema('category', 'Content category', ['support', 'sales', 'feedback', 'other']),
        new NumberSchema('confidence', 'Classification confidence'),
        new ArraySchema('tags', 'Relevant tags', new StringSchema('tag', 'A tag')),
    ],
    requiredFields: ['category', 'confidence'],
);

$response = Atlas::chat(
    'classifier',
    'I want to return my order and get a refund',
    schema: $schema,
);

// $response->structured = ['category' => 'support', 'confidence' => 0.92, 'tags' => ['refund', 'return']]
```

### Summarization

Get structured summaries:

```php
$schema = new ObjectSchema(
    name: 'summary',
    description: 'Article summary',
    properties: [
        new StringSchema('title', 'Suggested title'),
        new StringSchema('summary', 'Brief summary (2-3 sentences)'),
        new ArraySchema('key_points', 'Key takeaways', new StringSchema('point', 'A key point')),
        new EnumSchema('sentiment', 'Overall sentiment', ['positive', 'negative', 'neutral']),
    ],
    requiredFields: ['title', 'summary', 'key_points'],
);
```

## Best Practices

### 1. Provide Clear Descriptions

```php
// Good - specific descriptions
new StringSchema('email', 'A valid email address in format user@domain.com');

// Less helpful
new StringSchema('email', 'Email');
```

### 2. Use Required Fields Appropriately

Only mark fields as required if they're truly essential:

```php
new ObjectSchema(
    name: 'user',
    description: 'User profile',
    properties: [
        new StringSchema('name', 'Full name'),
        new StringSchema('email', 'Email address'),
        new StringSchema('phone', 'Phone number'),  // Optional
    ],
    requiredFields: ['name', 'email'],  // Phone is optional
);
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
