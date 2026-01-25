# MCP (Model Context Protocol)

Add external tools from MCP servers to your agents using prism-php/relay. MCP enables agents to use tools from external servers, expanding their capabilities beyond native Atlas tools.

::: tip Prism Relay
Atlas integrates with MCP through prism-php/relay. For complete MCP server configuration, transport options, and tool management, see the [Prism Relay documentation](https://prismphp.com/extras/relay.html).
:::

## Installation

Install the prism-php/relay package:

```bash
composer require prism-php/relay
```

## Configuration

Configure your MCP servers in `config/prism.php`. See [Prism Relay documentation](https://prismphp.com/extras/relay.html) for detailed configuration options.

```php
// config/prism.php
return [
    'relay' => [
        'servers' => [
            'filesystem' => [
                'command' => 'npx',
                'args' => ['-y', '@modelcontextprotocol/server-filesystem', '/path/to/allowed/dir'],
            ],
            'github' => [
                'command' => 'npx',
                'args' => ['-y', '@modelcontextprotocol/server-github'],
                'env' => [
                    'GITHUB_PERSONAL_ACCESS_TOKEN' => env('GITHUB_TOKEN'),
                ],
            ],
        ],
    ],
];
```

## Agent-Defined MCP Tools

Define MCP tools directly in your agent by overriding the `mcpTools()` method:

```php
use Atlasphp\Atlas\Agents\AgentDefinition;
use Prism\Relay\Relay;

class CodeAssistantAgent extends AgentDefinition
{
    public function __construct(
        private Relay $relay,
    ) {}

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
        return $this->relay->tools('filesystem');
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
use Prism\Relay\Relay;

$relay = app(Relay::class);

$response = Atlas::agent('general-assistant')
    ->withMcpTools($relay->tools('github'))
    ->chat('List open issues in the atlas-php/atlas repository');
```

### Accumulating Tools

Multiple calls to `withMcpTools()` accumulate tools:

```php
$response = Atlas::agent('research-agent')
    ->withMcpTools($relay->tools('filesystem'))
    ->withMcpTools($relay->tools('github'))
    ->chat('Find the README and check for related GitHub issues');
```

## Combined Usage

Combine agent-defined MCP tools with runtime tools for maximum flexibility:

```php
// Agent defines filesystem tools
class FileSystemAgent extends AgentDefinition
{
    public function __construct(private Relay $relay) {}

    public function mcpTools(): array
    {
        return $this->relay->tools('filesystem');
    }

    // ... other agent config
}

// Add GitHub tools at runtime
$relay = app(Relay::class);

$response = Atlas::agent(FileSystemAgent::class)
    ->withMcpTools($relay->tools('github'))
    ->chat('Compare local changes with the GitHub repository');
```

## With Streaming

MCP tools work with streaming responses:

```php
$relay = app(Relay::class);

$stream = Atlas::agent('assistant')
    ->withMcpTools($relay->tools('filesystem'))
    ->stream('Read and summarize the config file');

foreach ($stream as $event) {
    echo $event->text;
}
```

## Tool Priority

When tools are merged, they follow this order:

1. **Native Atlas tools** from `tools()` method
2. **Agent MCP tools** from `mcpTools()` method
3. **Runtime MCP tools** from `withMcpTools()` calls

All tools are passed to Prism together. If tools have conflicting names, the first occurrence takes precedence.

## Error Handling

MCP server errors are handled by Prism Relay. Common issues:

```php
use Prism\Relay\Exceptions\ServerNotFoundException;
use Prism\Relay\Exceptions\ConnectionException;

try {
    $tools = $relay->tools('unknown-server');
} catch (ServerNotFoundException $e) {
    // Server not configured
}

try {
    $response = Atlas::agent('assistant')
        ->withMcpTools($relay->tools('filesystem'))
        ->chat('List files');
} catch (ConnectionException $e) {
    // MCP server connection failed
}
```

See [Prism Relay documentation](https://prismphp.com/extras/relay.html) for complete error handling guidance.

## API Reference

```php
// Agent definition method
public function mcpTools(): array;  // Override to return Prism Tool instances

// Runtime method on PendingAgentRequest
->withMcpTools(array $tools): static;  // Add MCP tools, accumulates across calls

// ExecutionContext property
$context->mcpTools;        // array<int, \Prism\Prism\Tool>
$context->hasMcpTools();   // bool - check if MCP tools are present

// Relay methods (from prism-php/relay)
$relay->tools(string $serverName): array;  // Get tools from configured MCP server
$relay->tool(string $serverName, string $toolName): Tool;  // Get specific tool
```

## Next Steps

- [Tools](/core-concepts/tools) - Native Atlas tool definitions
- [Prism Relay documentation](https://prismphp.com/extras/relay.html) - Complete MCP configuration
- [Chat](/capabilities/chat) - Using tools in conversations
