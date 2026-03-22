# Artisan Commands

Atlas provides Artisan commands to scaffold agents and tools.

## make:agent

Generate a new agent class:

```bash
php artisan make:agent CustomerSupport
```

Creates `app/Agents/CustomerSupport.php`:

```php
use Atlasphp\Atlas\Agent;

class CustomerSupport extends Agent
{
    public function instructions(): ?string
    {
        return 'You are a helpful assistant.';
    }
}
```

### With Tools

```bash
php artisan make:agent CustomerSupport --tools
```

Includes a `tools()` method stub:

```php
class CustomerSupport extends Agent
{
    public function instructions(): ?string
    {
        return 'You are a helpful assistant.';
    }

    public function tools(): array
    {
        return [
            // \App\Tools\YourTool::class,
        ];
    }
}
```

### With Provider Tools

```bash
php artisan make:agent CustomerSupport --provider-tools
```

Includes a `providerTools()` method stub for native provider capabilities like web search.

### All Options Combined

```bash
php artisan make:agent CustomerSupport --tools --provider-tools
```

Includes both `tools()` and `providerTools()` methods.

### Options

| Option | Short | Description |
|--------|-------|-------------|
| `--tools` | `-t` | Include `tools()` method stub |
| `--provider-tools` | `-p` | Include `providerTools()` method stub |
| `--force` | `-f` | Overwrite if file already exists |

## make:tool

Generate a new tool class:

```bash
php artisan make:tool SearchProducts
```

Creates `app/Tools/SearchProducts.php`:

```php
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Tools\Tool;

class SearchProducts extends Tool
{
    public function name(): string
    {
        return 'search_products';
    }

    public function description(): string
    {
        return 'TODO: Describe what this tool does.';
    }

    public function parameters(): array
    {
        return [
            // Schema::string('query', 'The search query'),
            // Schema::integer('limit', 'Max results to return')->optional(),
        ];
    }

    public function handle(array $args, array $context): mixed
    {
        // TODO: Implement your tool logic here.

        return 'Tool result';
    }
}
```

### Tool Name Derivation

The tool name is automatically generated as `snake_case` from the class name. A `Tool` suffix is stripped:

| Class Name | Generated Tool Name |
|---|---|
| `SearchProducts` | `search_products` |
| `LookupOrderTool` | `lookup_order` |
| `SendEmail` | `send_email` |

### Options

| Option | Short | Description |
|--------|-------|-------------|
| `--force` | `-f` | Overwrite if file already exists |

## Auto-Discovery

Generated agents and tools are automatically discovered when auto-discovery is configured in `config/atlas.php`:

```php
'agents' => [
    'path' => app_path('Agents'),
    'namespace' => 'App\\Agents',
],
```

After scaffolding, use them immediately:

```php
// After: php artisan make:agent CustomerSupport
$response = Atlas::agent('customer-support')
    ->message('Hello')
    ->asText();
```
