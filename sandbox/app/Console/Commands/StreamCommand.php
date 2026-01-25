<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use Atlasphp\Atlas\Atlas;
use Illuminate\Console\Command;
use Prism\Prism\Streaming\Events\ErrorEvent;
use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\StreamStartEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Streaming\Events\ToolCallEvent;

/**
 * Command for testing streaming responses from agents.
 *
 * Demonstrates real-time streaming capabilities with event handling.
 */
class StreamCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'atlas:stream
                            {prompt : The message to send}
                            {--agent=general-assistant : Agent to use}
                            {--show-events : Show all event types, not just text}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Stream a response from an Atlas agent in real-time';

    /**
     * Execute the console command.
     */
    public function handle(AgentRegistryContract $agentRegistry): int
    {
        $agentKey = $this->option('agent');
        $prompt = $this->argument('prompt');
        $showEvents = $this->option('show-events');

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

        $this->displayHeader($agentKey, $agent->provider(), $agent->model());

        $this->info("Streaming response:\n");

        $eventCount = 0;
        $startTime = microtime(true);
        $finishReason = 'unknown';

        try {
            foreach (Atlas::agent($agentKey)->stream($prompt) as $event) {
                $eventCount++;

                if ($event instanceof TextDeltaEvent) {
                    $this->output->write($event->delta);
                } elseif ($event instanceof StreamEndEvent) {
                    $finishReason = $event->finishReason->value ?? 'unknown';
                    if ($showEvents) {
                        $this->info("\n[Stream ended: {$finishReason}]");
                    }
                } elseif ($showEvents) {
                    $this->displayEvent($event);
                }
            }
        } catch (\Throwable $e) {
            $this->newLine(2);
            $this->error("Stream error: {$e->getMessage()}");

            return self::FAILURE;
        }

        $duration = round(microtime(true) - $startTime, 2);

        $this->newLine(2);
        $this->info('--- Stream Statistics ---');
        $this->line("Events received: {$eventCount}");
        $this->line("Duration: {$duration}s");
        $this->line("Finish reason: {$finishReason}");
        $this->line('-------------------------');

        return self::SUCCESS;
    }

    /**
     * Display the command header.
     */
    protected function displayHeader(string $agentKey, ?string $provider, ?string $model): void
    {
        $this->line('');
        $this->line('=== Atlas Stream Demo ===');
        $this->line("Agent: {$agentKey} ({$provider}/{$model})");
        $this->line('');
    }

    /**
     * Display a non-text event.
     */
    protected function displayEvent(mixed $event): void
    {
        match (true) {
            $event instanceof StreamStartEvent => $this->info("\n[Stream started]"),
            $event instanceof ToolCallEvent => $this->displayToolCall($event),
            $event instanceof ErrorEvent => $this->error("\n[Error: {$event->message}]"),
            default => null,
        };
    }

    /**
     * Display tool call event details.
     */
    protected function displayToolCall(ToolCallEvent $event): void
    {
        $this->newLine();
        $this->warn('[TOOL CALL]');
        $this->line("  Tool Name: {$event->name}");
        $this->line("  Tool ID: {$event->id}");
        $this->line('  Arguments: '.json_encode($event->arguments));
    }
}
