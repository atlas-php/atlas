<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use Atlasphp\Atlas\Providers\Facades\Atlas;
use Atlasphp\Atlas\Streaming\Events\ErrorEvent;
use Atlasphp\Atlas\Streaming\Events\StreamEndEvent;
use Atlasphp\Atlas\Streaming\Events\StreamStartEvent;
use Atlasphp\Atlas\Streaming\Events\TextDeltaEvent;
use Atlasphp\Atlas\Streaming\Events\ToolCallEndEvent;
use Atlasphp\Atlas\Streaming\Events\ToolCallStartEvent;
use Illuminate\Console\Command;

/**
 * Command for testing streaming responses from agents.
 *
 * Demonstrates real-time streaming capabilities with event handling
 * and statistics display.
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

        $stream = Atlas::chat($agentKey, $prompt, stream: true);

        $eventCount = 0;
        $startTime = microtime(true);

        try {
            foreach ($stream as $event) {
                $eventCount++;

                if ($event instanceof TextDeltaEvent) {
                    $this->output->write($event->text);
                } elseif ($showEvents) {
                    $this->displayEvent($event);
                }
            }
        } catch (\Throwable $e) {
            $this->newLine(2);
            $this->error("Stream error: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->newLine(2);
        $this->displayStreamStats($stream, $eventCount, $startTime);

        return self::SUCCESS;
    }

    /**
     * Display the command header.
     */
    protected function displayHeader(string $agentKey, string $provider, string $model): void
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
            $event instanceof StreamStartEvent => $this->info("\n[Stream started: {$event->provider}/{$event->model}]"),
            $event instanceof ToolCallStartEvent => $this->displayToolCallStart($event),
            $event instanceof ToolCallEndEvent => $this->displayToolCallEnd($event),
            $event instanceof StreamEndEvent => $this->info("\n[Stream ended: {$event->finishReason}]"),
            $event instanceof ErrorEvent => $this->error("\n[Error: {$event->message}]"),
            default => null,
        };
    }

    /**
     * Display tool call start event details.
     */
    protected function displayToolCallStart(ToolCallStartEvent $event): void
    {
        $this->newLine();
        $this->warn('[TOOL CALL START]');
        $this->line("  Tool Name: {$event->toolName}");
        $this->line("  Tool ID: {$event->toolId}");
        $this->line('  Arguments: '.json_encode($event->arguments));
    }

    /**
     * Display tool call end event details.
     */
    protected function displayToolCallEnd(ToolCallEndEvent $event): void
    {
        $status = $event->success ? '<fg=green>success</>' : '<fg=red>failed</>';
        $this->info('[TOOL CALL END]');
        $this->line("  Tool Name: {$event->toolName}");
        $this->line("  Tool ID: {$event->toolId}");
        $this->line("  Status: {$status}");
        $this->line('  Result: '.(is_array($event->result) ? json_encode($event->result) : $event->result));
    }

    /**
     * Display stream statistics.
     */
    protected function displayStreamStats(mixed $stream, int $eventCount, float $startTime): void
    {
        $duration = round(microtime(true) - $startTime, 2);

        $this->info('--- Stream Statistics ---');
        $this->line("Events received: {$eventCount}");
        $this->line("Duration: {$duration}s");
        $this->line("Finish reason: {$stream->finishReason()}");

        $usage = $stream->usage();
        if ($usage !== []) {
            $this->line(sprintf(
                'Tokens: %d prompt / %d completion / %d total',
                $usage['prompt_tokens'] ?? 0,
                $usage['completion_tokens'] ?? 0,
                $usage['total_tokens'] ?? 0,
            ));
        }

        $toolCalls = $stream->toolCalls();
        if ($toolCalls !== []) {
            $this->line('Tool calls: '.count($toolCalls));
            foreach ($toolCalls as $i => $call) {
                $this->line("  [{$i}] {$call['name']}");
                $this->line('      ID: '.($call['id'] ?? 'N/A'));
                $this->line('      Args: '.json_encode($call['arguments'] ?? []));
                $this->line('      Result: '.(is_array($call['result'] ?? null) ? json_encode($call['result']) : ($call['result'] ?? 'N/A')));
            }
        }

        // Display all collected events summary
        $events = $stream->events();
        $this->line('');
        $this->info('--- Event Summary ---');
        $eventTypes = [];
        foreach ($events as $event) {
            $type = (new \ReflectionClass($event))->getShortName();
            $eventTypes[$type] = ($eventTypes[$type] ?? 0) + 1;
        }
        foreach ($eventTypes as $type => $count) {
            $this->line("  {$type}: {$count}");
        }

        $this->line('-------------------------');
    }
}
