<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use Atlasphp\Atlas\Atlas;
use Illuminate\Console\Command;
use Prism\Relay\Facades\Relay;

/**
 * Command for testing MCP tools integration with Atlas agents.
 *
 * Demonstrates MCP server configuration, tool listing, and tool execution
 * via the Prism Relay package. Always uses Atlas agents for testing.
 */
class McpCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'atlas:mcp
                            {--list-servers : List configured MCP servers}
                            {--list-tools : List tools from a server (requires --server)}
                            {--server=filesystem : MCP server name to use}
                            {--agent=general-assistant : Atlas agent to use}
                            {--prompt= : Prompt to test MCP tools with Atlas agent}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test MCP tools integration with Atlas agents';

    /**
     * Execute the console command.
     */
    public function handle(AgentRegistryContract $agentRegistry): int
    {
        $this->displayHeader();

        $server = $this->option('server');
        $agentKey = $this->option('agent');

        // Handle --list-servers
        if ($this->option('list-servers')) {
            return $this->listServers();
        }

        // Handle --list-tools
        if ($this->option('list-tools')) {
            return $this->listTools($server);
        }

        // Handle --prompt (test MCP tools with Atlas agent)
        $prompt = $this->option('prompt');
        if ($prompt) {
            // Verify agent exists
            if (! $agentRegistry->has($agentKey)) {
                $this->error("Agent not found: {$agentKey}");
                $this->line('');
                $this->info('Available agents:');
                foreach ($agentRegistry->keys() as $key) {
                    $this->line("  - {$key}");
                }

                return self::FAILURE;
            }

            return $this->testMcpWithAtlas($agentKey, $server, $prompt);
        }

        // No action specified - show help
        $this->showHelp();

        return self::SUCCESS;
    }

    /**
     * Display the command header.
     */
    protected function displayHeader(): void
    {
        $this->line('');
        $this->line('=== Atlas MCP Tools Test ===');
        $this->line('');
    }

    /**
     * List configured MCP servers.
     */
    protected function listServers(): int
    {
        $this->info('Configured MCP Servers:');
        $this->line('');

        $servers = config('relay.servers', []);

        if (empty($servers)) {
            $this->warn('No MCP servers configured.');
            $this->line('');
            $this->line('Add servers to config/relay.php');

            return self::SUCCESS;
        }

        $headers = ['Name', 'Transport', 'Command'];
        $rows = [];

        foreach ($servers as $name => $config) {
            $transport = $config['transport'] ?? 'unknown';
            $transportName = is_object($transport) ? $transport->value : (string) $transport;

            $command = $config['command'] ?? [];
            $commandStr = is_array($command) ? implode(' ', $command) : (string) $command;
            if (strlen($commandStr) > 50) {
                $commandStr = substr($commandStr, 0, 47).'...';
            }

            $rows[] = [$name, $transportName, $commandStr];
        }

        $this->table($headers, $rows);

        return self::SUCCESS;
    }

    /**
     * List tools from a specific MCP server.
     */
    protected function listTools(string $server): int
    {
        $this->info("Tools from MCP server: {$server}");
        $this->line('');

        try {
            $tools = Relay::tools($server);

            if (empty($tools)) {
                $this->warn('No tools available from this server.');

                return self::SUCCESS;
            }

            $headers = ['Name', 'Description'];
            $rows = [];

            foreach ($tools as $tool) {
                $rows[] = [
                    $tool->name(),
                    $this->truncate($tool->description(), 60),
                ];
            }

            $this->table($headers, $rows);
            $this->line('');
            $this->info('Total: '.count($tools).' tools');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed to list tools: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Test MCP tools with an Atlas agent.
     */
    protected function testMcpWithAtlas(string $agentKey, string $server, string $prompt): int
    {
        $this->info('Testing MCP Tools with Atlas Agent');
        $this->line('');
        $this->line("Agent: {$agentKey}");
        $this->line("MCP Server: {$server}");
        $this->line("Prompt: \"{$prompt}\"");
        $this->line('');

        try {
            // Load MCP tools from server
            $mcpTools = Relay::tools($server);
            $this->info('MCP Tools loaded: '.count($mcpTools));

            // List tool names
            $toolNames = array_map(fn ($t) => $t->name(), $mcpTools);
            $this->line('Available: '.implode(', ', array_slice($toolNames, 0, 5)).(count($toolNames) > 5 ? '...' : ''));
            $this->line('');

            // Execute with Atlas agent
            $response = Atlas::agent($agentKey)
                ->withMcpTools($mcpTools)
                ->chat($prompt);

            $this->displayResponse($response);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Display the response with tool call details.
     *
     * @param  \Atlasphp\Atlas\Agents\Support\AgentResponse  $response
     */
    protected function displayResponse($response): void
    {
        // Show tool calls from steps
        $this->line('=== MCP Tool Calls ===');
        $toolCallCount = 0;

        foreach ($response->steps() as $step) {
            if (! property_exists($step, 'toolCalls') || ! is_array($step->toolCalls)) {
                continue;
            }

            foreach ($step->toolCalls as $call) {
                $toolCallCount++;
                $args = json_decode($call->arguments, true);

                $this->info("[{$toolCallCount}] {$call->name}");
                $this->line('    Args: '.json_encode($args, JSON_UNESCAPED_SLASHES));

                // Find and show result
                if (property_exists($step, 'toolResults') && is_array($step->toolResults)) {
                    foreach ($step->toolResults as $result) {
                        if ($result->toolCallId === $call->id) {
                            $resultStr = $result->result;
                            if (strlen($resultStr) > 200) {
                                $resultStr = substr($resultStr, 0, 200).'...';
                            }
                            $this->line("    Result: {$resultStr}");
                            break;
                        }
                    }
                }

                $this->line('');
            }
        }

        if ($toolCallCount === 0) {
            $this->warn('No MCP tool calls were made.');
            $this->line('');
        }

        // Show final response
        $this->line('=== Agent Response ===');
        $this->line($response->text());
        $this->line('');

        // Show verification
        $this->line('=== Verification ===');
        if ($toolCallCount > 0) {
            $this->info("[PASS] MCP tools were called ({$toolCallCount} calls)");
        } else {
            $this->warn('[WARN] No MCP tools were called');
        }

        $usage = $response->usage();
        $this->line(sprintf(
            'Tokens: %d prompt / %d completion / %d total',
            $usage->promptTokens,
            $usage->completionTokens,
            $usage->promptTokens + $usage->completionTokens,
        ));
    }

    /**
     * Show command help.
     */
    protected function showHelp(): void
    {
        $this->line('Usage:');
        $this->line('');
        $this->line('  List configured MCP servers:');
        $this->line('    php artisan atlas:mcp --list-servers');
        $this->line('');
        $this->line('  List tools from a server:');
        $this->line('    php artisan atlas:mcp --list-tools');
        $this->line('    php artisan atlas:mcp --server=filesystem --list-tools');
        $this->line('');
        $this->line('  Test MCP tools with Atlas agent:');
        $this->line('    php artisan atlas:mcp --prompt="List files in storage"');
        $this->line('    php artisan atlas:mcp --agent=general-assistant --prompt="List files"');
        $this->line('    php artisan atlas:mcp --agent=tool-demo --server=filesystem --prompt="Read a file"');
    }

    /**
     * Truncate a string to a maximum length.
     */
    protected function truncate(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length - 3).'...';
    }
}
