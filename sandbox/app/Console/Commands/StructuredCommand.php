<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlasphp\Atlas\Providers\Facades\Atlas;
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Schema\SchemaBuilder;
use Illuminate\Console\Command;

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
     * @var array<string, array{prompt: string, builder: callable, useJsonMode?: bool, requiredFields: array<string>}>
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
        $schema = ($schemaConfig['builder'])();
        $defaultPrompt = $schemaConfig['prompt'];
        $useJsonMode = $schemaConfig['useJsonMode'] ?? false;
        $requiredFields = $schemaConfig['requiredFields'];

        // Use provided prompt or default
        $prompt = $prompt ?: $defaultPrompt;

        $this->displayHeader($schemaKey, $prompt, $useJsonMode);
        $this->displaySchemaDefinition($schema);

        try {
            $this->info('Extracting structured data...');
            $this->line('');

            $agent = Atlas::agent('structured-output')->withSchema($schema);

            // Use JSON mode for schemas with optional fields
            if ($useJsonMode) {
                $agent = $agent->usingJsonMode();
            }

            $response = $agent->chat($prompt);

            $this->displayResponse($response);
            $this->displayVerification($response, $requiredFields);

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
                'builder' => fn () => Schema::object('person', 'Information about a person')
                    ->string('name', 'The person\'s full name')
                    ->number('age', 'The person\'s age in years')
                    ->string('email', 'The person\'s email address')
                    ->string('occupation', 'The person\'s job or occupation')
                    ->build(),
                'requiredFields' => ['name', 'age', 'email', 'occupation'],
            ],

            // Schema with boolean field
            'product' => [
                'prompt' => 'Extract product info: The iPhone 15 Pro costs $999 in the Electronics category and is currently in stock.',
                'builder' => fn () => Schema::object('product', 'Information about a product')
                    ->string('name', 'The product name')
                    ->number('price', 'The product price in dollars')
                    ->string('category', 'The product category')
                    ->boolean('inStock', 'Whether the product is in stock')
                    ->build(),
                'requiredFields' => ['name', 'price', 'category', 'inStock'],
            ],

            // Schema with string arrays
            'review' => [
                'prompt' => 'Extract review: "Amazing product! 5 stars. The quality is excellent and shipping was fast. Pros: Great value, durable. Cons: A bit heavy."',
                'builder' => fn () => Schema::object('review', 'A product review')
                    ->number('rating', 'The rating from 1 to 5')
                    ->string('title', 'A short title for the review')
                    ->string('body', 'The main review text')
                    ->stringArray('pros', 'List of positive points')
                    ->stringArray('cons', 'List of negative points')
                    ->build(),
                'requiredFields' => ['rating', 'title', 'body', 'pros', 'cons'],
            ],

            // Schema with nested objects and arrays of objects
            'order' => [
                'prompt' => 'Extract order: Order #ORD-12345 from customer Jane Doe (jane@example.com). Items: 2x Widget at $19.99, 1x Gadget at $49.99. Total: $89.97',
                'builder' => fn () => Schema::object('order', 'An order with customer and items')
                    ->string('orderId', 'The order identifier')
                    ->number('total', 'Total order amount in dollars')
                    ->object('customer', 'Customer information', fn (SchemaBuilder $s) => $s
                        ->string('name', 'Customer full name')
                        ->string('email', 'Customer email address')
                    )
                    ->array('items', 'Ordered items', fn (SchemaBuilder $s) => $s
                        ->string('name', 'Item name')
                        ->number('quantity', 'Quantity ordered')
                        ->number('price', 'Unit price in dollars')
                    )
                    ->build(),
                'requiredFields' => ['orderId', 'total', 'customer', 'items'],
            ],

            // Schema with enum field
            'sentiment' => [
                'prompt' => 'Analyze sentiment: "I absolutely love this product! It exceeded all my expectations and I would highly recommend it to everyone."',
                'builder' => fn () => Schema::object('sentiment', 'Sentiment analysis result')
                    ->enum('sentiment', 'The detected sentiment', ['positive', 'negative', 'neutral'])
                    ->number('confidence', 'Confidence score from 0 to 1')
                    ->string('summary', 'Brief explanation of the sentiment')
                    ->stringArray('keywords', 'Key words that indicate sentiment')
                    ->build(),
                'requiredFields' => ['sentiment', 'confidence', 'summary', 'keywords'],
            ],

            // Schema with optional fields - uses JSON mode
            'contact' => [
                'prompt' => 'Extract contact: Reach out to Mike Johnson at mike@company.com. He works at TechCorp.',
                'builder' => fn () => Schema::object('contact', 'Contact information')
                    ->string('name', 'Full name')
                    ->string('email', 'Email address')
                    ->string('phone', 'Phone number')->optional()
                    ->string('company', 'Company name')->optional()
                    ->string('title', 'Job title')->optional()
                    ->build(),
                'useJsonMode' => true, // JSON mode supports optional fields
                'requiredFields' => ['name', 'email'], // Only name and email are required
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
            'contact' => 'Schema with optional fields (uses JSON mode)',
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
    protected function displayHeader(string $schemaKey, string $prompt, bool $useJsonMode = false): void
    {
        $this->line('');
        $this->line('=== Atlas Structured Output Test ===');
        $this->line('Agent: structured-output');
        $this->line("Schema: {$schemaKey}");

        if ($useJsonMode) {
            $this->line('Mode: JSON (allows optional fields)');
        }

        $this->line('');
        $this->line("Prompt: \"{$prompt}\"");
        $this->line('');
    }

    /**
     * Display the schema definition using toArray().
     *
     * @param  mixed  $schema  The Prism ObjectSchema.
     */
    protected function displaySchemaDefinition($schema): void
    {
        $this->line('--- Schema Definition ---');
        $this->line(json_encode($schema->toArray(), JSON_PRETTY_PRINT));
        $this->line('');
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
     * @param  array<string>  $requiredFields
     */
    protected function displayVerification($response, array $requiredFields): void
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
        $this->info('[PASS] Response has structured data');

        // Check required fields
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

        $this->info('[PASS] Structured output extraction complete');
    }
}
