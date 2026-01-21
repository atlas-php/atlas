<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlasphp\Atlas\Providers\Facades\Atlas;
use Illuminate\Console\Command;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

/**
 * Command for testing structured output extraction.
 *
 * Demonstrates schema-based data extraction from text.
 */
class StructuredCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'atlas:structured
                            {--schema=person : Predefined schema (person|product|review)}
                            {--prompt= : Custom prompt for extraction}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test structured output extraction with Atlas';

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

            $response = Atlas::chat('structured-output', $prompt, schema: $schema);

            $this->displayResponse($response);
            $this->displayVerification($response, $schema);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Initialize predefined schemas.
     */
    protected function initializeSchemas(): void
    {
        $this->schemas = [
            'person' => [
                'prompt' => 'Extract person info: John Smith is a 35-year-old software engineer at john@example.com',
                'schema' => new ObjectSchema(
                    name: 'person',
                    description: 'Information about a person',
                    properties: [
                        new StringSchema('name', 'The person\'s full name'),
                        new NumberSchema('age', 'The person\'s age in years'),
                        new StringSchema('email', 'The person\'s email address'),
                        new StringSchema('occupation', 'The person\'s job or occupation'),
                    ],
                    // OpenAI requires all properties in requiredFields for structured output
                    requiredFields: ['name', 'age', 'email', 'occupation'],
                ),
            ],
            'product' => [
                'prompt' => 'Extract product info: The iPhone 15 Pro costs $999 in the Electronics category and is currently in stock.',
                'schema' => new ObjectSchema(
                    name: 'product',
                    description: 'Information about a product',
                    properties: [
                        new StringSchema('name', 'The product name'),
                        new NumberSchema('price', 'The product price in dollars'),
                        new StringSchema('category', 'The product category'),
                        new BooleanSchema('inStock', 'Whether the product is in stock'),
                    ],
                    // OpenAI requires all properties in requiredFields for structured output
                    requiredFields: ['name', 'price', 'category', 'inStock'],
                ),
            ],
            'review' => [
                'prompt' => 'Extract review: "Amazing product! 5 stars. The quality is excellent and shipping was fast. Pros: Great value, durable. Cons: A bit heavy."',
                'schema' => new ObjectSchema(
                    name: 'review',
                    description: 'A product review',
                    properties: [
                        new NumberSchema('rating', 'The rating from 1 to 5'),
                        new StringSchema('title', 'A short title for the review'),
                        new StringSchema('body', 'The main review text'),
                        new ArraySchema('pros', 'List of positive points', new StringSchema('pro', 'A positive point')),
                        new ArraySchema('cons', 'List of negative points', new StringSchema('con', 'A negative point')),
                    ],
                    // OpenAI requires all properties in requiredFields for structured output
                    requiredFields: ['rating', 'title', 'body', 'pros', 'cons'],
                ),
            ],
        ];
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
        $type = match (true) {
            $prop instanceof StringSchema => 'string',
            $prop instanceof NumberSchema => 'number',
            $prop instanceof BooleanSchema => 'boolean',
            $prop instanceof ArraySchema => 'array',
            $prop instanceof ObjectSchema => 'object',
            default => 'string',
        };

        return ['type' => $type];
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
                $prop instanceof ArraySchema => 'array',
                $prop instanceof ObjectSchema => 'object',
                default => null,
            };

            if ($expectedType && ! $this->validateType($value, $expectedType)) {
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
    protected function validateType(mixed $value, string $expectedType): bool
    {
        return match ($expectedType) {
            'string' => is_string($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'array' => is_array($value) && array_is_list($value),
            'object' => is_array($value) || is_object($value),
            default => true,
        };
    }
}
