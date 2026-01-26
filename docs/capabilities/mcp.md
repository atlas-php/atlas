# MCP (Model Context Protocol)

Add external tools from MCP servers to your agents using prism-php/relay. MCP enables agents to use tools from external servers, expanding their capabilities beyond native Atlas tools.

::: tip Prism Relay
Atlas integrates with MCP through prism-php/relay. For complete MCP server configuration, transport options, and tool management, see the [Prism Relay repository](https://github.com/prism-php/relay).
:::

## Installation

Install the prism-php/relay package:

```bash
composer require prism-php/relay
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="relay-config"
```

## Configuration

Configure your MCP servers in `config/relay.php`:

```php
// config/relay.php
use Prism\Relay\Enums\Transport;

return [
    'servers' => [
        'filesystem' => [
            'transport' => Transport::Stdio,
            'command' => ['npx', '-y', '@modelcontextprotocol/server-filesystem', '/path/to/allowed/dir'],
            'timeout' => 30,
        ],
        'github' => [
            'transport' => Transport::Stdio,
            'command' => ['npx', '-y', '@modelcontextprotocol/server-github'],
            'env' => [
                'GITHUB_PERSONAL_ACCESS_TOKEN' => env('GITHUB_TOKEN'),
            ],
            'timeout' => 30,
        ],
    ],
    'cache_duration' => 60, // minutes, 0 to disable
];
```

## Agent-Defined MCP Tools

Define MCP tools directly in your agent by overriding the `mcpTools()` method:

```php
use Atlasphp\Atlas\Agents\AgentDefinition;
use Prism\Relay\Facades\Relay;

class CodeAssistantAgent extends AgentDefinition
{
    public function provider(): ?string
    {
        return 'anthropic';
    }

    public function model(): ?string
    {
        return 'claude-sonnet-4-20250514';
    }

    public function systemPrompt(): ?string
    {
        return 'You are a code assistant with access to the filesystem.';
    }

    public function mcpTools(): array
    {
        return Relay::tools('filesystem');
    }
}
```

Use the agent normally:

```php
use Atlasphp\Atlas\Atlas;

$response = Atlas::agent(CodeAssistantAgent::class)
    ->chat('List the files in the project directory');
```

## Runtime MCP Tools

Add MCP tools at runtime using `withMcpTools()`:

```php
use Atlasphp\Atlas\Atlas;
use Prism\Relay\Facades\Relay;

$response = Atlas::agent('general-assistant')
    ->withMcpTools(Relay::tools('github'))
    ->chat('List open issues in the atlas-php/atlas repository');
```

### Accumulating Tools

Multiple calls to `withMcpTools()` accumulate tools:

```php
use Prism\Relay\Facades\Relay;

$response = Atlas::agent('research-agent')
    ->withMcpTools(Relay::tools('filesystem'))
    ->withMcpTools(Relay::tools('github'))
    ->chat('Find the README and check for related GitHub issues');
```

## Combined Usage

Combine agent-defined MCP tools with runtime tools for maximum flexibility:

```php
use Atlasphp\Atlas\Agents\AgentDefinition;
use Prism\Relay\Facades\Relay;

// Agent defines filesystem tools
class FileSystemAgent extends AgentDefinition
{
    public function mcpTools(): array
    {
        return Relay::tools('filesystem');
    }

    // ... other agent config
}

// Add GitHub tools at runtime
$response = Atlas::agent(FileSystemAgent::class)
    ->withMcpTools(Relay::tools('github'))
    ->chat('Compare local changes with the GitHub repository');
```

## With Streaming

MCP tools work with streaming responses:

```php
use Prism\Relay\Facades\Relay;

$stream = Atlas::agent('assistant')
    ->withMcpTools(Relay::tools('filesystem'))
    ->stream('Read and summarize the config file');

foreach ($stream as $event) {
    echo $event->text;
}
```

## Tool Priority

When tools are merged, they follow this order:

1. **Agent native tools** from `tools()` method
2. **Runtime native tools** from `withTools()` calls
3. **Agent MCP tools** from `mcpTools()` method
4. **Runtime MCP tools** from `withMcpTools()` calls

All tools are passed to Prism together. If tools have conflicting names, the first occurrence takes precedence.

## Error Handling

MCP server errors are handled by Prism Relay. Common issues:

```php
use Prism\Relay\Exceptions\ServerNotFoundException;
use Prism\Relay\Exceptions\ConnectionException;

try {
    $tools = Relay::tools('unknown-server');
} catch (ServerNotFoundException $e) {
    // Server not configured
}

try {
    $response = Atlas::agent('assistant')
        ->withMcpTools(Relay::tools('filesystem'))
        ->chat('List files');
} catch (ConnectionException $e) {
    // MCP server connection failed
}
```

See the [Prism Relay repository](https://github.com/prism-php/relay) for complete error handling guidance.

## API Reference

```php
// Agent definition methods
public function mcpTools(): array;  // Override to return Prism Tool instances

// Runtime methods on PendingAgentRequest
->withTools(array $tools): static;     // Add Atlas tools at runtime, accumulates
->withMcpTools(array $tools): static;  // Add MCP tools at runtime, accumulates

// AgentContext properties
$context->tools;           // array<int, class-string<ToolContract>>
$context->hasTools();      // bool - check if runtime tools are present
$context->mcpTools;        // array<int, \Prism\Prism\Tool>
$context->hasMcpTools();   // bool - check if MCP tools are present

// Relay facade methods (from prism-php/relay)
Relay::tools(string $serverName): array;  // Get tools from configured MCP server
Relay::tool(string $serverName, string $toolName): Tool;  // Get specific tool
```

## Next Steps

- [Tools](/core-concepts/tools) - Native Atlas tool definitions
- [Prism Relay](https://github.com/prism-php/relay) - Complete MCP server configuration
- [Chat](/capabilities/chat) - Using tools in conversations
