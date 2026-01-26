<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Pipelines\FilterToolsHandler;
use App\Pipelines\InjectMetadataHandler;
use App\Pipelines\LogExecutionHandler;
use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Pipelines\PipelineRegistry;
use Illuminate\Console\Command;

/**
 * Command for testing pipeline context manipulation.
 *
 * Demonstrates how pipelines can inject metadata, filter tools,
 * and observe agent execution.
 */
class PipelineCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'atlas:pipeline
                            {--demo=all : Demo to run: all, context, tools, log}
                            {--agent=tool-demo : Agent to use for testing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test pipeline context manipulation with Atlas agents';

    /**
     * Execute the console command.
     */
    public function handle(
        AgentRegistryContract $agentRegistry,
        PipelineRegistry $pipelineRegistry,
    ): int {
        $demo = $this->option('demo');
        $agentKey = $this->option('agent');

        // Verify agent exists
        if (! $agentRegistry->has($agentKey)) {
            $this->error("Agent not found: {$agentKey}");

            return self::FAILURE;
        }

        $this->displayHeader($demo);

        try {
            return match ($demo) {
                'context' => $this->runContextDemo($agentKey, $pipelineRegistry),
                'tools' => $this->runToolsDemo($agentKey, $pipelineRegistry),
                'log' => $this->runLogDemo($agentKey, $pipelineRegistry),
                'all' => $this->runAllDemos($agentKey, $pipelineRegistry),
                default => $this->invalidDemo($demo),
            };
        } catch (\Throwable $e) {
            $this->error("Error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Display the command header.
     */
    protected function displayHeader(string $demo): void
    {
        $this->line('');
        $this->line('=== Atlas Pipeline Demo ===');
        $this->line("Demo: {$demo}");
        $this->line('');
    }

    /**
     * Run all demos.
     *
     * Note: Pipeline handlers accumulate across demos. For isolated testing,
     * run each demo individually with --demo=context, --demo=tools, --demo=log.
     */
    protected function runAllDemos(string $agentKey, PipelineRegistry $pipelineRegistry): int
    {
        $this->info('=== Running All Pipeline Demos ===');
        $this->line('');
        $this->warn('Note: Pipeline handlers accumulate. Run demos individually for isolated testing.');
        $this->line('');

        $this->runContextDemo($agentKey, $pipelineRegistry);
        $this->line('');
        $this->line(str_repeat('-', 50));
        $this->line('');

        $this->runToolsDemo($agentKey, $pipelineRegistry);
        $this->line('');
        $this->line(str_repeat('-', 50));
        $this->line('');

        $this->runLogDemo($agentKey, $pipelineRegistry);

        return self::SUCCESS;
    }

    /**
     * Run the context injection demo.
     */
    protected function runContextDemo(string $agentKey, PipelineRegistry $pipelineRegistry): int
    {
        $this->info('--- Demo: Context Injection ---');
        $this->line('');
        $this->line('This demo shows how pipelines can inject metadata into the agent context.');
        $this->line('The InjectMetadataHandler adds custom metadata before agent execution.');
        $this->line('');

        // Register the metadata injection handler
        $pipelineRegistry->register(
            'agent.before_execute',
            InjectMetadataHandler::class,
            priority: 100
        );

        $prompt = 'What is 2 + 2?';
        $this->line("Prompt: \"{$prompt}\"");
        $this->line('');

        $response = Atlas::agent($agentKey)->chat($prompt);

        $this->line('=== Response ===');
        $this->line($response->text());
        $this->line('');

        $this->line('=== Injected Metadata ===');
        $metadata = $response->metadata();

        if (empty($metadata)) {
            $this->warn('No metadata found in response.');
        } else {
            foreach ($metadata as $key => $value) {
                $valueStr = is_array($value) ? json_encode($value) : (string) $value;
                $this->line("  {$key}: {$valueStr}");
            }
        }

        $this->line('');
        $this->info('[PASS] Context injection demo completed');

        return self::SUCCESS;
    }

    /**
     * Run the tool filtering demo.
     */
    protected function runToolsDemo(string $agentKey, PipelineRegistry $pipelineRegistry): int
    {
        $this->info('--- Demo: Tool Filtering ---');
        $this->line('');
        $this->line('This demo shows how pipelines can filter available tools.');
        $this->line('The FilterToolsHandler restricts tools to only the calculator.');
        $this->line('');

        // Register the tool filter handler (only allow calculator)
        $pipelineRegistry->register(
            'agent.tools.merged',
            new FilterToolsHandler(['calculator']),
            priority: 100
        );

        $prompt = 'Calculate 7847 divided by 23 using the calculator tool. You MUST use the tool.';
        $this->line("Prompt: \"{$prompt}\"");
        $this->line('Allowed Tools: calculator (weather and datetime filtered out)');
        $this->line('');

        $response = Atlas::agent($agentKey)->chat($prompt);

        $this->line('=== Response ===');
        $this->line($response->text());
        $this->line('');

        // Show which tools were called (tool calls are in steps)
        $this->line('=== Tool Calls ===');

        $calledTools = [];
        foreach ($response->steps() as $step) {
            if (property_exists($step, 'toolCalls') && is_array($step->toolCalls)) {
                foreach ($step->toolCalls as $call) {
                    $calledTools[] = $call->name;
                    $args = json_decode($call->arguments, true);
                    $this->line("  - {$call->name}: ".json_encode($args));
                }
            }
        }

        if (empty($calledTools)) {
            $this->warn('No tool calls made.');
        }

        $this->line('');

        // Verify only calculator was available (weather and datetime should be filtered out)
        $filteredOut = array_intersect(['weather', 'datetime'], $calledTools);

        if (empty($filteredOut)) {
            $this->info('[PASS] Tool filtering demo completed - only allowed tools used');
        } else {
            $this->warn('[WARN] Filtered tools were still called: '.implode(', ', $filteredOut));
        }

        return self::SUCCESS;
    }

    /**
     * Run the execution logging demo.
     */
    protected function runLogDemo(string $agentKey, PipelineRegistry $pipelineRegistry): int
    {
        $this->info('--- Demo: Execution Logging ---');
        $this->line('');
        $this->line('This demo shows how pipelines can observe execution for logging/auditing.');
        $this->line('The LogExecutionHandler captures execution details after completion.');
        $this->line('');

        // Enable capture mode so we can display the logs
        LogExecutionHandler::enableCapture();
        LogExecutionHandler::clearCapturedLogs();

        // Register the logging handler
        $pipelineRegistry->register(
            'agent.after_execute',
            LogExecutionHandler::class,
            priority: 100
        );

        $prompt = 'What is the current time in UTC?';
        $this->line("Prompt: \"{$prompt}\"");
        $this->line('');

        $response = Atlas::agent($agentKey)->chat($prompt);

        $this->line('=== Response ===');
        $this->line($response->text());
        $this->line('');

        // Display captured logs
        $this->line('=== Captured Log Entries ===');
        $logs = LogExecutionHandler::getCapturedLogs();

        if (empty($logs)) {
            $this->warn('No log entries captured.');
        } else {
            foreach ($logs as $log) {
                $this->line("[{$log['level']}] {$log['message']}");
                foreach ($log['context'] as $key => $value) {
                    $valueStr = is_array($value) ? json_encode($value) : (string) $value;
                    $this->line("    {$key}: {$valueStr}");
                }
            }
        }

        LogExecutionHandler::disableCapture();

        $this->line('');
        $this->info('[PASS] Execution logging demo completed');

        return self::SUCCESS;
    }

    /**
     * Handle invalid demo option.
     */
    protected function invalidDemo(string $demo): int
    {
        $this->error("Invalid demo: {$demo}");
        $this->line('');
        $this->line('Available demos:');
        $this->line('  all     - Run all demos');
        $this->line('  context - Test context/metadata injection');
        $this->line('  tools   - Test tool filtering');
        $this->line('  log     - Test execution logging');

        return self::FAILURE;
    }
}
