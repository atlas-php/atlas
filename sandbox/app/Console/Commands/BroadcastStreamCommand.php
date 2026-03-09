<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use Atlasphp\Atlas\Agents\Events\AgentStreamChunk;
use Atlasphp\Atlas\Agents\Support\AgentStreamResponse;
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Streaming\SseStreamFormatter;
use Atlasphp\Atlas\Streaming\VercelStreamProtocol;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Prism\Prism\Streaming\Events\ErrorEvent;
use Prism\Prism\Streaming\Events\StreamEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Throwable;

/**
 * Command for testing streaming protocols and broadcast streaming.
 *
 * Demonstrates consumer-friendly usage of:
 * - Direct stream iteration (default)
 * - SSE wire format output
 * - Vercel AI SDK Data Stream Protocol output
 * - Broadcast streaming via WebSocket queues
 */
class BroadcastStreamCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'atlas:broadcast-stream
                            {prompt : The message to send}
                            {--agent=general-assistant : Agent to use}
                            {--mode=stream : Output mode: stream, sse, vercel, broadcast, inline-broadcast}
                            {--request-id= : Custom request ID for broadcast mode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test streaming protocols (direct, SSE, Vercel AI SDK) and broadcast streaming';

    /**
     * Execute the console command.
     */
    public function handle(AgentRegistryContract $agentRegistry): int
    {
        $agentKey = $this->option('agent');
        $prompt = $this->argument('prompt');
        $mode = $this->option('mode');

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
        $this->line('=== Atlas Streaming Protocol Demo ===');
        $this->line("Agent: {$agentKey} ({$agent->provider()}/{$agent->model()})");
        $this->line("Mode: {$mode}");
        $this->line("Prompt: {$prompt}");
        $this->line('');

        try {
            return match ($mode) {
                'stream' => $this->runStreamDemo($agentKey, $prompt),
                'sse' => $this->runSseDemo($agentKey, $prompt),
                'vercel' => $this->runVercelDemo($agentKey, $prompt),
                'broadcast' => $this->runBroadcastDemo($agentKey, $prompt),
                'inline-broadcast' => $this->runInlineBroadcastDemo($agentKey, $prompt),
                default => $this->invalidMode($mode),
            };
        } catch (Throwable $e) {
            $this->error("Error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Demo: Direct stream iteration with each() and onError() callbacks.
     */
    private function runStreamDemo(string $agentKey, string $prompt): int
    {
        $this->info('Direct stream iteration with each() and onError()...');
        $this->line('');

        $eventLog = [];

        $stream = Atlas::agent($agentKey)->stream($prompt)
            ->each(function (StreamEvent $event) use (&$eventLog) {
                $eventLog[] = $event->eventKey();
            })
            ->onError(function (ErrorEvent $error) {
                $this->error("[Error: {$error->errorType}] {$error->message}");
            });

        foreach ($stream as $event) {
            if ($event instanceof TextDeltaEvent) {
                $this->output->write($event->delta);
            }
        }

        $this->newLine(2);
        $this->info('Event log from each(): '.implode(', ', $eventLog));
        $this->displayStreamStats($stream);

        // Demonstrate replay
        $this->info('Replaying stream from cache...');
        $replayCount = 0;
        foreach ($stream as $event) {
            $replayCount++;
        }
        $this->line("Replayed {$replayCount} events.");

        return self::SUCCESS;
    }

    /**
     * Demo: SSE wire format — shows what toResponse() sends to browsers.
     */
    private function runSseDemo(string $agentKey, string $prompt): int
    {
        $this->info('SSE wire format (what toResponse() sends to the browser)...');
        $this->line('');

        $stream = Atlas::agent($agentKey)->stream($prompt);
        $formatter = new SseStreamFormatter;

        $this->info('Headers: Content-Type: text/event-stream');
        $this->line('---');

        // Iterate the stream and show the SSE wire format
        foreach ($stream as $event) {
            $this->output->write($formatter->format($event));
        }
        $this->output->write($formatter->done());

        $this->line('---');
        $this->displayStreamStats($stream);

        return self::SUCCESS;
    }

    /**
     * Demo: Vercel AI SDK Data Stream Protocol wire format.
     */
    private function runVercelDemo(string $agentKey, string $prompt): int
    {
        $this->info('Vercel AI SDK Data Stream Protocol wire format...');
        $this->line('');

        $stream = Atlas::agent($agentKey)->stream($prompt);
        $protocol = new VercelStreamProtocol;

        $this->info('Headers: Content-Type: text/plain; charset=utf-8');
        $this->line('---');

        // Iterate the stream and show the Vercel wire format
        foreach ($stream as $event) {
            $formatted = $protocol->format($event);
            if ($formatted !== null) {
                $this->output->write($formatted);
            }
        }

        $this->line('---');
        $this->displayStreamStats($stream);

        return self::SUCCESS;
    }

    /**
     * Demo: Broadcast streaming (WebSocket delivery via queue).
     */
    private function runBroadcastDemo(string $agentKey, string $prompt): int
    {
        $requestId = $this->option('request-id') ?? Str::random(32);

        $this->warn('Broadcast mode requires Horizon running: php artisan horizon');
        $this->line('');
        $this->line("Request ID: {$requestId}");
        $this->line("Channel: atlas.agent.{$agentKey}.{$requestId}");
        $this->line('');

        // Listen for broadcast events locally to show they fire
        $chunks = [];
        Event::listen(AgentStreamChunk::class, function (AgentStreamChunk $chunk) use (&$chunks) {
            $chunks[] = $chunk;
        });

        $this->info('Dispatching broadcast job...');

        Atlas::agent($agentKey)->broadcast($prompt, $requestId);

        $this->info('Job dispatched!');
        $this->line('');

        if ($chunks !== []) {
            $this->info('Broadcast events captured (sync driver):');
            foreach ($chunks as $chunk) {
                $this->line("  [{$chunk->type}] ".($chunk->delta ?? json_encode($chunk->metadata)));
            }
        } else {
            $this->line('No events captured locally (expected with async queue driver).');
            $this->line('Events will broadcast on the WebSocket channel.');
        }

        $this->line('');
        $this->info('Frontend listener example:');
        $this->line("  Echo.private('atlas.agent.{$agentKey}.{$requestId}')");
        $this->line("    .listen('.atlas.stream.chunk', (e) => console.log(e));");

        return self::SUCCESS;
    }

    /**
     * Demo: Inline broadcast — broadcastNow() during stream iteration.
     */
    private function runInlineBroadcastDemo(string $agentKey, string $prompt): int
    {
        $requestId = $this->option('request-id') ?? Str::random(32);

        $this->info('Inline broadcast (broadcastNow) — no queue required...');
        $this->line('');
        $this->line("Request ID: {$requestId}");
        $this->line("Channel: atlas.agent.{$agentKey}.{$requestId}");
        $this->line('');

        // Listen for broadcast events locally
        $chunks = [];
        Event::listen(AgentStreamChunk::class, function (AgentStreamChunk $chunk) use (&$chunks) {
            $chunks[] = $chunk;
        });

        $stream = Atlas::agent($agentKey)->stream($prompt)
            ->broadcastNow($requestId)
            ->each(function (StreamEvent $event) {
                if ($event instanceof TextDeltaEvent) {
                    $this->output->write($event->delta);
                }
            });

        // Consume the stream — events are broadcast synchronously during iteration
        iterator_to_array($stream);

        $this->newLine(2);

        if ($chunks !== []) {
            $this->info('Broadcast '.count($chunks).' events captured:');
            foreach ($chunks as $chunk) {
                $this->line("  [{$chunk->type}] ".($chunk->delta ?? '(no delta)'));
            }
        }

        $this->displayStreamStats($stream);

        return self::SUCCESS;
    }

    /**
     * Display stream statistics after consumption.
     */
    private function displayStreamStats(AgentStreamResponse $stream): void
    {
        $this->line('');
        $this->info('--- Stream Statistics ---');
        $this->line('Events collected: '.count($stream->events()));
        $this->line('Full text: '.mb_substr($stream->text(), 0, 100).(mb_strlen($stream->text()) > 100 ? '...' : ''));
        $this->line('Consumed: '.($stream->isConsumed() ? 'yes' : 'no'));
        $this->line('-------------------------');
    }

    /**
     * Handle invalid mode.
     */
    private function invalidMode(string $mode): int
    {
        $this->error("Invalid mode: {$mode}");
        $this->line('Valid modes: stream, sse, vercel, broadcast, inline-broadcast');

        return self::FAILURE;
    }
}
