<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Atlas;
use Illuminate\Console\Command;
use Throwable;

/**
 * Command for testing queued/async agent execution.
 *
 * Demonstrates the queue() API for background agent execution
 * with then()/catch() callbacks.
 */
class QueueCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'atlas:queue
                            {prompt : The message to send}
                            {--agent=general-assistant : Agent to use}
                            {--queue=default : Queue name to dispatch to}
                            {--sync : Run synchronously instead of queuing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue an agent execution for async processing (requires Horizon)';

    /**
     * Execute the console command.
     */
    public function handle(AgentRegistryContract $agentRegistry): int
    {
        $agentKey = $this->option('agent');
        $prompt = $this->argument('prompt');
        $queueName = $this->option('queue');
        $sync = $this->option('sync');

        // Verify agent exists
        if (! $agentRegistry->has($agentKey)) {
            $this->error("Agent not found: {$agentKey}");
            $this->line('');
            $this->info('Available agents:');
            foreach ($agentRegistry->keys() as $key) {
                $agent = $agentRegistry->get($key);
                $this->line("  - {$key} ({$agent->provider()}/{$agent->model()})");
            }

            return self::FAILURE;
        }

        $agent = $agentRegistry->get($agentKey);

        $this->line('');
        $this->line('=== Atlas Queue Demo ===');
        $this->line("Agent: {$agentKey} ({$agent->provider()}/{$agent->model()})");
        $this->line("Queue: {$queueName}");
        $this->line('Mode: '.($sync ? 'synchronous' : 'async (requires Horizon)'));
        $this->line("Prompt: {$prompt}");
        $this->line('');

        if (! $sync) {
            $this->warn('Make sure Horizon is running: php artisan horizon');
            $this->line('');
        }

        $resultFile = storage_path('outputs/queue-result-'.time().'.txt');

        try {
            if ($sync) {
                $this->info('Executing synchronously...');
                $response = Atlas::agent($agentKey)->chat($prompt);
                $this->displayResponse($response);
            } else {
                $this->info('Dispatching to queue...');

                Atlas::agent($agentKey)
                    ->queue($prompt)
                    ->onQueue($queueName)
                    ->then(function (AgentResponse $response) use ($resultFile) {
                        $content = "Agent: {$response->agentKey()}\n";
                        $content .= "Text: {$response->text()}\n";
                        $content .= "Tokens: {$response->usage()->promptTokens} prompt / {$response->usage()->completionTokens} completion\n";
                        file_put_contents($resultFile, $content);
                    })
                    ->catch(function (Throwable $e) use ($resultFile) {
                        file_put_contents($resultFile, "ERROR: {$e->getMessage()}\n");
                    });

                $this->info('Job dispatched successfully!');
                $this->line('');
                $this->line("Result will be written to: {$resultFile}");
                $this->line('');
                $this->info('Check the result with:');
                $this->line("  cat {$resultFile}");
                $this->line('');
                $this->info('Or watch for it:');
                $this->line("  watch -n 1 cat {$resultFile}");
            }
        } catch (Throwable $e) {
            $this->error("Error: {$e->getMessage()}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Display a synchronous response.
     */
    private function displayResponse(AgentResponse $response): void
    {
        $this->line('');
        $this->info('=== Response ===');
        $this->line($response->text());
        $this->line('');
        $this->info('--- Details ---');
        $this->line("Agent: {$response->agentKey()}");
        $this->line("Tokens: {$response->usage()->promptTokens} prompt / {$response->usage()->completionTokens} completion");
        $this->line("Finish: {$response->response->finishReason->value}");
        $this->line('');
    }
}
