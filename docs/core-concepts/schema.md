# Schema

The Schema system provides a PHP-native way to build JSON Schema structures. It's used for tool parameters and structured output definitions.

## Entry Point

All schema building starts with the `Schema` class:

```php
use Atlasphp\Atlas\Schema\Schema;

// Create field instances
$name = Schema::string('name', 'The user name');
$age = Schema::integer('age', 'The user age');

// Build a full schema for structured output
$schema = Schema::object('user', 'A user profile')
    ->string('name', 'Full name')
    ->integer('age', 'Age in years')
    ->string('email', 'Email address')
    ->build();
```

## Field Types

### String

```php
Schema::string('name', 'The user name')
```

Produces: `{ "type": "string", "description": "The user name" }`

### Integer

```php
Schema::integer('age', 'Age in years')
```

Produces: `{ "type": "integer", "description": "Age in years" }`

### Number

```php
Schema::number('price', 'Price in dollars')
```

Produces: `{ "type": "number", "description": "Price in dollars" }`

### Boolean

```php
Schema::boolean('active', 'Whether the account is active')
```

Produces: `{ "type": "boolean", "description": "Whether the account is active" }`

### Enum

```php
Schema::enum('status', 'Order status', ['pending', 'shipped', 'delivered'])
```

Produces: `{ "type": "string", "description": "Order status", "enum": ["pending", "shipped", "delivered"] }`

### String Array

```php
Schema::stringArray('tags', 'List of tags')
```

Produces: `{ "type": "array", "description": "List of tags", "items": { "type": "string" } }`

### Number Array

```php
Schema::numberArray('scores', 'List of scores')
```

Produces: `{ "type": "array", "description": "List of scores", "items": { "type": "number" } }`

### Object Array

```php
Schema::array('items', 'Line items', function ($builder) {
    $builder->string('name', 'Item name')
        ->number('price', 'Item price')
        ->integer('quantity', 'Quantity ordered');
})
```

Produces an array of objects, each with `name`, `price`, and `quantity` properties.

### Object

```php
Schema::object('address', 'Mailing address')
    ->string('street', 'Street address')
    ->string('city', 'City')
    ->string('state', 'State code')
    ->string('zip', 'ZIP code')
```

## Optional Fields

By default, all fields are **required**. Chain `->optional()` to make a field optional:

```php
Schema::object('contact', 'Contact info')
    ->string('name', 'Full name')            // required
    ->string('email', 'Email address')       // required
    ->string('phone', 'Phone number')
    ->optional()                              // makes 'phone' optional
```

In the fluent builder chain, `->optional()` applies to the **last added field**.

For standalone fields used in tool parameters:

```php
public function parameters(): array
{
    return [
        Schema::string('query', 'Search query'),                    // required
        Schema::string('category', 'Filter category')->optional(),  // optional
        Schema::integer('limit', 'Max results')->optional(),        // optional
    ];
}
```

## Nested Objects

Objects can nest other objects:

```php
Schema::object('order', 'An order')
    ->string('id', 'Order ID')
    ->object('customer', 'Customer details', function ($obj) {
        $obj->string('name', 'Customer name')
            ->string('email', 'Customer email');
    })
    ->array('items', 'Line items', function ($builder) {
        $builder->string('product', 'Product name')
            ->integer('quantity', 'Quantity')
            ->number('price', 'Unit price');
    })
```

## Usage in Tools

Tool parameters are defined using Schema fields:

```php
use Atlasphp\Atlas\Tools\Tool;
use Atlasphp\Atlas\Schema\Schema;

class SearchTool extends Tool
{
    public function name(): string { return 'search'; }
    public function description(): string { return 'Search for products'; }

    public function parameters(): array
    {
        return [
            Schema::string('query', 'Search query'),
            Schema::enum('category', 'Product category', ['electronics', 'clothing', 'books']),
            Schema::integer('limit', 'Max results')->optional(),
        ];
    }

    public function handle(array $args, array $context): mixed
    {
        return Product::search($args['query'])
            ->when($args['category'] ?? null, fn ($q, $cat) => $q->where('category', $cat))
            ->take($args['limit'] ?? 10)
            ->get()
            ->toArray();
    }
}
```

## Usage in Structured Output

Build a complete schema for structured responses:

```php
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Facades\Atlas;

$schema = Schema::object('analysis', 'Sentiment analysis result')
    ->enum('sentiment', 'Overall sentiment', ['positive', 'negative', 'neutral'])
    ->number('confidence', 'Confidence score 0-1')
    ->stringArray('keywords', 'Key topics identified')
    ->string('summary', 'Brief summary')
    ->build();

$response = Atlas::agent('analyst')
    ->message('Analyze this review: "Great product, fast shipping!"')
    ->withSchema($schema)
    ->asStructured();

$data = $response->structured;
// ['sentiment' => 'positive', 'confidence' => 0.95, 'keywords' => ['product', 'shipping'], 'summary' => '...']
```

The `->build()` method converts the fluent object builder into a `Schema` value object that can be passed to `->withSchema()`.

## API Reference

### Static Factories (`Schema::`)

| Method | Returns | JSON Schema Type |
|--------|---------|-----------------|
| `string($name, $description)` | `StringField` | `string` |
| `integer($name, $description)` | `IntegerField` | `integer` |
| `number($name, $description)` | `NumberField` | `number` |
| `boolean($name, $description)` | `BooleanField` | `boolean` |
| `enum($name, $description, $options)` | `EnumField` | `string` with `enum` |
| `stringArray($name, $description)` | `ArrayField` | `array` of `string` |
| `numberArray($name, $description)` | `ArrayField` | `array` of `number` |
| `array($name, $description, $callback)` | `ArrayField` | `array` of `object` |
| `object($name, $description)` | `ObjectField` | `object` |

### Field Methods

| Method | Description |
|--------|-------------|
| `->optional()` | Mark as not required (default is required) |
| `->isRequired()` | Check if field is required |
| `->name()` | Get field name |
| `->description()` | Get field description |
| `->toSchema()` | Convert to JSON Schema array |

### ObjectField Methods

| Method | Description |
|--------|-------------|
| `->string($name, $desc)` | Add a string property |
| `->integer($name, $desc)` | Add an integer property |
| `->number($name, $desc)` | Add a number property |
| `->boolean($name, $desc)` | Add a boolean property |
| `->enum($name, $desc, $options)` | Add an enum property |
| `->stringArray($name, $desc)` | Add a string array property |
| `->numberArray($name, $desc)` | Add a number array property |
| `->array($name, $desc, $callback)` | Add an object array property |
| `->object($name, $desc, $callback)` | Add a nested object property |
| `->optional()` | Mark last added property as optional |
| `->build()` | Convert to `Schema` value object |

## Next Steps

- [Tools](/core-concepts/tools) — Use schema fields for tool parameters
- [Text](/capabilities/text) — Structured output with `->withSchema()` and `->asStructured()`
