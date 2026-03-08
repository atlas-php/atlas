# Artisan Commands

Atlas provides artisan commands to scaffold new agents and tools quickly.

## make:tool

Generate a new tool class:

```bash
php artisan make:tool SearchProducts
```

This creates `app/Tools/SearchProducts.php` with a ready-to-use skeleton:

```php
class SearchProducts extends ToolDefinition
{
    public function name(): string
    {
        return 'search_products';
    }

    public function description(): string
    {
        return 'A description of what this tool does.';
    }

    public function parameters(): array
    {
        return [
            ToolParameter::string('query', 'The input query', required: true),
        ];
    }

    public function handle(array $params, ToolContext $context): ToolResult
    {
        // Implement your tool logic here

        return ToolResult::text('Result');
    }
}
```

The tool name is automatically generated as `snake_case` from the class name. A `Tool` suffix is stripped before conversion:

| Class Name | Generated Tool Name |
|---|---|
| `SearchProducts` | `search_products` |
| `LookupOrderTool` | `lookup_order` |
| `SendEmail` | `send_email` |

### Options

| Option | Description |
|---|---|
| `--force`, `-f` | Overwrite the file if it already exists |

## make:agent

Generate a new agent class:

```bash
php artisan make:agent CustomerSupport
```

This creates `app/Agents/CustomerSupport.php`:

```php
class CustomerSupport extends AgentDefinition
{
    public function systemPrompt(): ?string
    {
        return 'You are a helpful assistant.';
    }

    public function tools(): array
    {
        return [];
    }
}
```

The generated agent includes `systemPrompt()` and `tools()` — the two methods you'll most commonly override. All other methods have sensible defaults in `AgentDefinition`.

### Options

| Option | Description |
|---|---|
| `--force`, `-f` | Overwrite the file if it already exists |

## Auto-Discovery

Generated agents and tools are automatically discovered and registered. No manual registration is needed — just create the class and use it:

```php
// After: php artisan make:agent CustomerSupport
$response = Atlas::agent('customer-support')->chat('Hello');

// After: php artisan make:tool SearchProducts
// Reference in your agent's tools() method
public function tools(): array
{
    return [SearchProducts::class];
}
```

Configure discovery paths in `config/atlas.php`:

```php
'agents' => [
    'path' => app_path('Agents'),
    'namespace' => 'App\\Agents',
],

'tools' => [
    'path' => app_path('Tools'),
    'namespace' => 'App\\Tools',
],
```

