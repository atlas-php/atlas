<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlasphp\Atlas\Providers\Facades\Atlas;
use Atlasphp\Atlas\Schema\Schema;
use Illuminate\Console\Command;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

/**
 * Command for testing structured output extraction.
 *
 * Demonstrates schema-based data extraction from text using the Schema Builder.
 */
class StructuredCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'atlas:structured
                            {--schema=person : Predefined schema (person|product|review|order|sentiment|contact)}
                            {--prompt= : Custom prompt for extraction}
                            {--list : List all available schemas}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test structured output extraction with Atlas Schema Builder';

    /**
     * Predefined schemas and prompts.
     *
     * @var array<string, array{prompt: string, schema: ObjectSchema}>
     */
    protected array $schemas = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->initializeSchemas();

        if ($this->option('list')) {
            $this->listSchemas();

            return self::SUCCESS;
        }

        $schemaKey = $this->option('schema');
        $prompt = $this->option('prompt');

        if (! isset($this->schemas[$schemaKey])) {
            $this->error("Unknown schema: {$schemaKey}");
            $this->line('Available schemas: '.implode(', ', array_keys($this->schemas)));

            return self::FAILURE;
        }

        $schemaConfig = $this->schemas[$schemaKey];
        $schema = $schemaConfig['schema'];
        $defaultPrompt = $schemaConfig['prompt'];

        // Use provided prompt or default
        $prompt = $prompt ?: $defaultPrompt;

        $this->displayHeader($schemaKey, $prompt);
        $this->displaySchemaDefinition($schema);

        try {
            $this->info('Extracting structured data...');
            $this->line('');

            $response = Atlas::agent('structured-output')
                ->withSchema($schema)
                ->chat($prompt);

            $this->displayResponse($response);
            $this->displayVerification($response, $schema);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Initialize predefined schemas using the Schema Builder.
     */
    protected function initializeSchemas(): void
    {
        $this->schemas = [
            // Basic schema with string and number fields
            'person' => [
                'prompt' => 'Extract person info: John Smith is a 35-year-old software engineer at john@example.com',
                'schema' => Schema::object('person', 'Information about a person')
                    ->string('name', 'The person\'s full name')
                    ->number('age', 'The person\'s age in years')
                    ->string('email', 'The person\'s email address')
                    ->string('occupation', 'The person\'s job or occupation')
                    ->build(),
            ],

            // Schema with boolean field
            'product' => [
                'prompt' => 'Extract product info: The iPhone 15 Pro costs $999 in the Electronics category and is currently in stock.',
                'schema' => Schema::object('product', 'Information about a product')
                    ->string('name', 'The product name')
                    ->number('price', 'The product price in dollars')
                    ->string('category', 'The product category')
                    ->boolean('inStock', 'Whether the product is in stock')
                    ->build(),
            ],

            // Schema with string arrays
            'review' => [
                'prompt' => 'Extract review: "Amazing product! 5 stars. The quality is excellent and shipping was fast. Pros: Great value, durable. Cons: A bit heavy."',
                'schema' => Schema::object('review', 'A product review')
                    ->number('rating', 'The rating from 1 to 5')
                    ->string('title', 'A short title for the review')
                    ->string('body', 'The main review text')
                    ->stringArray('pros', 'List of positive points')
                    ->stringArray('cons', 'List of negative points')
                    ->build(),
            ],

            // Schema with nested objects and arrays of objects
            'order' => [
                'prompt' => 'Extract order: Order #ORD-12345 from customer Jane Doe (jane@example.com). Items: 2x Widget at $19.99, 1x Gadget at $49.99. Total: $89.97',
                'schema' => Schema::object('order', 'An order with customer and items')
                    ->string('orderId', 'The order identifier')
                    ->number('total', 'Total order amount in dollars')
                    ->object('customer', 'Customer information', fn ($s) => $s
                        ->string('name', 'Customer full name')
                        ->string('email', 'Customer email address')
                    )
                    ->array('items', 'Ordered items', fn ($s) => $s
                        ->string('name', 'Item name')
                        ->number('quantity', 'Quantity ordered')
                        ->number('price', 'Unit price in dollars')
                    )
                    ->build(),
            ],

            // Schema with enum field
            'sentiment' => [
                'prompt' => 'Analyze sentiment: "I absolutely love this product! It exceeded all my expectations and I would highly recommend it to everyone."',
                'schema' => Schema::object('sentiment', 'Sentiment analysis result')
                    ->enum('sentiment', 'The detected sentiment', ['positive', 'negative', 'neutral'])
                    ->number('confidence', 'Confidence score from 0 to 1')
                    ->string('summary', 'Brief explanation of the sentiment')
                    ->stringArray('keywords', 'Key words that indicate sentiment')
                    ->build(),
            ],

            // Schema with all required fields (OpenAI structured output requires all fields to be required)
            'contact' => [
                'prompt' => 'Extract contact: Reach out to Mike Johnson at mike@company.com or call 555-1234. He works at TechCorp as a Product Manager.',
                'schema' => Schema::object('contact', 'Contact information')
                    ->string('name', 'Full name')
                    ->string('email', 'Email address')
                    ->string('phone', 'Phone number')
                    ->string('company', 'Company name')
                    ->string('title', 'Job title')
                    ->build(),
            ],
        ];
    }

    /**
     * List all available schemas.
     */
    protected function listSchemas(): void
    {
        $this->line('');
        $this->line('=== Available Schemas ===');
        $this->line('');

        $descriptions = [
            'person' => 'Basic schema with string and number fields',
            'product' => 'Schema with boolean field',
            'review' => 'Schema with string arrays (pros/cons)',
            'order' => 'Schema with nested objects and arrays of objects',
            'sentiment' => 'Schema with enum field for classification',
            'contact' => 'Schema with multiple string fields',
        ];

        foreach ($this->schemas as $key => $config) {
            $desc = $descriptions[$key] ?? 'Custom schema';
            $this->line("<info>{$key}</info> - {$desc}");
        }

        $this->line('');
        $this->line('Usage: php artisan atlas:structured --schema=<name>');
    }

    /**
     * Display the command header.
     */
    protected function displayHeader(string $schemaKey, string $prompt): void
    {
        $this->line('');
        $this->line('=== Atlas Structured Output Test ===');
        $this->line('Agent: structured-output');
        $this->line("Schema: {$schemaKey}");
        $this->line('');
        $this->line("Prompt: \"{$prompt}\"");
        $this->line('');
    }

    /**
     * Display the schema definition.
     */
    protected function displaySchemaDefinition(ObjectSchema $schema): void
    {
        $this->line('--- Schema Definition ---');

        // Build a simplified schema representation
        $schemaArray = $this->schemaToArray($schema);
        $this->line(json_encode($schemaArray, JSON_PRETTY_PRINT));
        $this->line('');
    }

    /**
     * Convert schema to array for display.
     *
     * @return array<string, mixed>
     */
    protected function schemaToArray(ObjectSchema $schema): array
    {
        $properties = [];
        $required = [];

        foreach ($this->getSchemaProperties($schema) as $prop) {
            $propSchema = $this->propertyToSchema($prop);
            $properties[$prop->name()] = $propSchema;
        }

        foreach ($this->getSchemaRequiredFields($schema) as $field) {
            $required[] = $field;
        }

        $result = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if (! empty($required)) {
            $result['required'] = $required;
        }

        return $result;
    }

    /**
     * Convert a property to schema array.
     *
     * @return array<string, mixed>
     */
    protected function propertyToSchema(object $prop): array
    {
        $schema = match (true) {
            $prop instanceof StringSchema => ['type' => 'string'],
            $prop instanceof NumberSchema => ['type' => 'number'],
            $prop instanceof BooleanSchema => ['type' => 'boolean'],
            $prop instanceof EnumSchema => [
                'type' => 'string',
                'enum' => $prop->options,
            ],
            $prop instanceof ArraySchema => [
                'type' => 'array',
                'items' => $this->propertyToSchema($prop->items),
            ],
            $prop instanceof ObjectSchema => $this->schemaToArray($prop),
            default => ['type' => 'string'],
        };

        if ($prop instanceof StringSchema || $prop instanceof NumberSchema || $prop instanceof BooleanSchema) {
            if ($prop->nullable) {
                $schema['nullable'] = true;
            }
        }

        return $schema;
    }

    /**
     * Get properties from schema using reflection.
     *
     * @return array<object>
     */
    protected function getSchemaProperties(ObjectSchema $schema): array
    {
        try {
            $reflection = new \ReflectionClass($schema);
            $property = $reflection->getProperty('properties');

            return $property->getValue($schema);
        } catch (\ReflectionException) {
            return [];
        }
    }

    /**
     * Get required fields from schema using reflection.
     *
     * @return array<string>
     */
    protected function getSchemaRequiredFields(ObjectSchema $schema): array
    {
        try {
            $reflection = new \ReflectionClass($schema);
            $property = $reflection->getProperty('requiredFields');

            return $property->getValue($schema);
        } catch (\ReflectionException) {
            return [];
        }
    }

    /**
     * Display the structured response.
     *
     * @param  \Atlasphp\Atlas\Agents\Support\AgentResponse  $response
     */
    protected function displayResponse($response): void
    {
        $this->line('--- Structured Response ---');

        $structured = $response->structured;

        if ($structured === null) {
            $this->warn('No structured data returned.');
            if ($response->hasText()) {
                $this->line("Text response: {$response->text}");
            }

            return;
        }

        $json = is_string($structured)
            ? $structured
            : json_encode($structured, JSON_PRETTY_PRINT);

        $this->line($json);
        $this->line('');
    }

    /**
     * Display verification results.
     *
     * @param  \Atlasphp\Atlas\Agents\Support\AgentResponse  $response
     */
    protected function displayVerification($response, ObjectSchema $schema): void
    {
        $this->line('--- Verification ---');

        $structured = $response->structured;

        if ($structured === null) {
            $this->error('[FAIL] No structured response returned');

            return;
        }

        // Convert to array if object
        $data = is_array($structured) ? $structured : (array) $structured;

        // Check structure matches
        $this->info('[PASS] Response matches schema structure');

        // Check required fields
        $requiredFields = $this->getSchemaRequiredFields($schema);
        $missingRequired = [];

        foreach ($requiredFields as $field) {
            if (! array_key_exists($field, $data)) {
                $missingRequired[] = $field;
            }
        }

        if (empty($missingRequired)) {
            $this->info('[PASS] Required fields present: '.implode(', ', $requiredFields));
        } else {
            $this->error('[FAIL] Missing required fields: '.implode(', ', $missingRequired));
        }

        // Check field types (basic validation)
        $properties = $this->getSchemaProperties($schema);
        $typeErrors = [];

        foreach ($properties as $prop) {
            $name = $prop->name();
            if (! array_key_exists($name, $data)) {
                continue;
            }

            $value = $data[$name];
            $expectedType = match (true) {
                $prop instanceof StringSchema => 'string',
                $prop instanceof NumberSchema => 'number',
                $prop instanceof BooleanSchema => 'boolean',
                $prop instanceof EnumSchema => 'enum',
                $prop instanceof ArraySchema => 'array',
                $prop instanceof ObjectSchema => 'object',
                default => null,
            };

            if ($expectedType && ! $this->validateType($value, $expectedType, $prop)) {
                $typeErrors[] = "{$name} (expected {$expectedType})";
            }
        }

        if (empty($typeErrors)) {
            $this->info('[PASS] Field types are correct');
        } else {
            $this->error('[FAIL] Type mismatches: '.implode(', ', $typeErrors));
        }

        // Check for extra fields
        $schemaFields = array_map(fn ($p) => $p->name(), $properties);
        $extraFields = array_diff(array_keys($data), $schemaFields);

        if (empty($extraFields)) {
            $this->info('[PASS] No extra fields in response');
        } else {
            $this->warn('[WARN] Extra fields in response: '.implode(', ', $extraFields));
        }
    }

    /**
     * Validate a value against an expected type.
     */
    protected function validateType(mixed $value, string $expectedType, object $prop): bool
    {
        return match ($expectedType) {
            'string' => is_string($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'enum' => $prop instanceof EnumSchema && in_array($value, $prop->options, true),
            'array' => is_array($value) && array_is_list($value),
            'object' => is_array($value) || is_object($value),
            default => true,
        };
    }
}
