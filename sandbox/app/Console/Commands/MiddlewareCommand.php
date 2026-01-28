<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Contracts\PipelineContract;
use Closure;
use Illuminate\Console\Command;

/**
 * Command for testing runtime middleware on agent requests.
 *
 * Demonstrates how to use the ->middleware() method to attach
 * per-request pipeline handlers without global registration.
 */
class MiddlewareCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'atlas:middleware
                            {--demo=all : Demo to run: all, logging, isolation, ordering, context}
                            {--agent=general-assistant : Agent to use for testing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test runtime middleware on Atlas agent requests';

    /**
     * Middleware execution log for demos.
     *
     * @var array<int, array{event: string, timestamp: float, data: array<string, mixed>}>
     */
    protected array $middlewareLog = [];

    /**
     * Execute the console command.
     */
    public function handle(AgentRegistryContract $agentRegistry): int
    {
        $demo = $this->option('demo');
        $agentKey = $this->option('agent');

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

        $this->displayHeader($demo, $agentKey);

        try {
            return match ($demo) {
                'logging' => $this->runLoggingDemo($agentKey),
                'isolation' => $this->runIsolationDemo($agentKey),
                'ordering' => $this->runOrderingDemo($agentKey),
                'context' => $this->runContextDemo($agentKey),
                'all' => $this->runAllDemos($agentKey),
                default => $this->invalidDemo($demo),
            };
        } catch (\Throwable $e) {
            $this->error("Error: {$e->getMessage()}");
            $this->line($e->getTraceAsString());

            return self::FAILURE;
        }
    }

    /**
     * Display the command header.
     */
    protected function displayHeader(string $demo, string $agentKey): void
    {
        $this->line('');
        $this->line('=== Atlas Runtime Middleware Demo ===');
        $this->line("Demo: {$demo}");
        $this->line("Agent: {$agentKey}");
        $this->line('');
    }

    /**
     * Run all demos.
     */
    protected function runAllDemos(string $agentKey): int
    {
        $this->info('=== Running All Runtime Middleware Demos ===');
        $this->line('');

        $this->runLoggingDemo($agentKey);
        $this->line('');
        $this->line(str_repeat('-', 60));
        $this->line('');

        $this->runIsolationDemo($agentKey);
        $this->line('');
        $this->line(str_repeat('-', 60));
        $this->line('');

        $this->runOrderingDemo($agentKey);
        $this->line('');
        $this->line(str_repeat('-', 60));
        $this->line('');

        $this->runContextDemo($agentKey);

        return self::SUCCESS;
    }

    /**
     * Demo: Before/After logging middleware.
     */
    protected function runLoggingDemo(string $agentKey): int
    {
        $this->info('--- Demo: Before/After Logging Middleware ---');
        $this->line('');
        $this->line('This demo shows runtime middleware that logs before and after execution.');
        $this->line('Unlike global pipelines, this middleware only applies to this single request.');
        $this->line('');

        $this->middlewareLog = [];
        $log = &$this->middlewareLog;

        // Create before middleware
        $beforeMiddleware = new class($log) implements PipelineContract
        {
            public function __construct(private array &$log) {}

            public function handle(mixed $data, Closure $next): mixed
            {
                $this->log[] = [
                    'event' => 'before_execute',
                    'timestamp' => microtime(true),
                    'data' => [
                        'agent' => $data['agent']->key(),
                        'input' => $data['input'],
                        'has_context' => isset($data['context']),
                    ],
                ];

                return $next($data);
            }
        };

        // Create after middleware
        $afterMiddleware = new class($log) implements PipelineContract
        {
            public function __construct(private array &$log) {}

            public function handle(mixed $data, Closure $next): mixed
            {
                $this->log[] = [
                    'event' => 'after_execute',
                    'timestamp' => microtime(true),
                    'data' => [
                        'has_response' => isset($data['response']),
                        'response_length' => isset($data['response']) ? strlen($data['response']->text) : 0,
                        'finish_reason' => isset($data['response']) ? $data['response']->finishReason->value : null,
                    ],
                ];

                return $next($data);
            }
        };

        $prompt = 'Say "hello world" in exactly two words.';
        $this->line("Prompt: \"{$prompt}\"");
        $this->line('');

        // Execute with runtime middleware
        $response = Atlas::agent($agentKey)
            ->middleware([
                'agent.before_execute' => $beforeMiddleware,
                'agent.after_execute' => $afterMiddleware,
            ])
            ->chat($prompt);

        $this->line('=== Response ===');
        $this->line($response->text());
        $this->line('');

        $this->line('=== Middleware Log ===');
        foreach ($this->middlewareLog as $entry) {
            $this->line("[{$entry['event']}] at ".number_format($entry['timestamp'], 4));
            foreach ($entry['data'] as $key => $value) {
                $valueStr = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
                $this->line("    {$key}: {$valueStr}");
            }
        }

        // Calculate duration
        if (count($this->middlewareLog) >= 2) {
            $duration = $this->middlewareLog[1]['timestamp'] - $this->middlewareLog[0]['timestamp'];
            $this->line('');
            $this->line(sprintf('API call duration: %.2f seconds', $duration));
        }

        $this->line('');
        $this->info('[PASS] Logging middleware demo completed');

        return self::SUCCESS;
    }

    /**
     * Demo: Middleware isolation between requests.
     */
    protected function runIsolationDemo(string $agentKey): int
    {
        $this->info('--- Demo: Middleware Isolation ---');
        $this->line('');
        $this->line('This demo verifies that runtime middleware only applies to the request it\'s attached to.');
        $this->line('A second request without middleware should not trigger the middleware.');
        $this->line('');

        $executionCount = 0;

        $countingMiddleware = new class($executionCount) implements PipelineContract
        {
            public function __construct(private int &$count) {}

            public function handle(mixed $data, Closure $next): mixed
            {
                $this->count++;

                return $next($data);
            }
        };

        // First request WITH middleware
        $this->line('Request 1: WITH middleware');
        $response1 = Atlas::agent($agentKey)
            ->middleware([
                'agent.before_execute' => $countingMiddleware,
            ])
            ->chat('Say "first" in one word.');

        $this->line("  Response: {$response1->text()}");
        $this->line("  Middleware count: {$executionCount}");
        $this->line('');

        // Second request WITHOUT middleware
        $this->line('Request 2: WITHOUT middleware');
        $response2 = Atlas::agent($agentKey)
            ->chat('Say "second" in one word.');

        $this->line("  Response: {$response2->text()}");
        $this->line("  Middleware count: {$executionCount} (should still be 1)");
        $this->line('');

        if ($executionCount === 1) {
            $this->info('[PASS] Middleware isolation verified - count remained at 1');
        } else {
            $this->error("[FAIL] Middleware isolation failed - count is {$executionCount}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Demo: Multiple middleware execution order.
     */
    protected function runOrderingDemo(string $agentKey): int
    {
        $this->info('--- Demo: Middleware Execution Order ---');
        $this->line('');
        $this->line('This demo shows that multiple middleware handlers execute in registration order.');
        $this->line('');

        $order = [];

        $firstMiddleware = new class($order) implements PipelineContract
        {
            public function __construct(private array &$order) {}

            public function handle(mixed $data, Closure $next): mixed
            {
                $this->order[] = 'first';

                return $next($data);
            }
        };

        $secondMiddleware = new class($order) implements PipelineContract
        {
            public function __construct(private array &$order) {}

            public function handle(mixed $data, Closure $next): mixed
            {
                $this->order[] = 'second';

                return $next($data);
            }
        };

        $thirdMiddleware = new class($order) implements PipelineContract
        {
            public function __construct(private array &$order) {}

            public function handle(mixed $data, Closure $next): mixed
            {
                $this->order[] = 'third';

                return $next($data);
            }
        };

        $prompt = 'Say "order test" in two words.';
        $this->line("Prompt: \"{$prompt}\"");
        $this->line('');

        $response = Atlas::agent($agentKey)
            ->middleware([
                'agent.before_execute' => [$firstMiddleware, $secondMiddleware, $thirdMiddleware],
            ])
            ->chat($prompt);

        $this->line('=== Response ===');
        $this->line($response->text());
        $this->line('');

        $this->line('=== Execution Order ===');
        $this->line('  '.implode(' -> ', $order));
        $this->line('');

        $expectedOrder = ['first', 'second', 'third'];
        if ($order === $expectedOrder) {
            $this->info('[PASS] Middleware executed in correct order');
        } else {
            $this->error('[FAIL] Middleware order incorrect');
            $this->line('  Expected: '.implode(' -> ', $expectedOrder));
            $this->line('  Actual: '.implode(' -> ', $order));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Demo: Middleware context modification.
     */
    protected function runContextDemo(string $agentKey): int
    {
        $this->info('--- Demo: Context Modification via Middleware ---');
        $this->line('');
        $this->line('This demo shows middleware can modify the agent context (e.g., inject metadata).');
        $this->line('');

        $capturedMetadata = null;

        // Middleware that injects metadata
        $injectingMiddleware = new class implements PipelineContract
        {
            public function handle(mixed $data, Closure $next): mixed
            {
                $data['context'] = $data['context']->mergeMetadata([
                    'injected_by_middleware' => true,
                    'injection_timestamp' => time(),
                    'request_id' => uniqid('req_'),
                ]);

                return $next($data);
            }
        };

        // Middleware that captures metadata after execution
        $capturingMiddleware = new class($capturedMetadata) implements PipelineContract
        {
            public function __construct(private mixed &$captured) {}

            public function handle(mixed $data, Closure $next): mixed
            {
                $this->captured = $data['context']->metadata;

                return $next($data);
            }
        };

        $prompt = 'Say "metadata test" in two words.';
        $this->line("Prompt: \"{$prompt}\"");
        $this->line('Original metadata: {original_key: "original_value"}');
        $this->line('');

        $response = Atlas::agent($agentKey)
            ->withMetadata(['original_key' => 'original_value'])
            ->middleware([
                'agent.before_execute' => $injectingMiddleware,
                'agent.after_execute' => $capturingMiddleware,
            ])
            ->chat($prompt);

        $this->line('=== Response ===');
        $this->line($response->text());
        $this->line('');

        $this->line('=== Captured Metadata After Middleware ===');
        if ($capturedMetadata) {
            foreach ($capturedMetadata as $key => $value) {
                $valueStr = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
                $this->line("  {$key}: {$valueStr}");
            }
        } else {
            $this->warn('No metadata captured.');
        }
        $this->line('');

        // Verify injection worked
        if (isset($capturedMetadata['original_key']) && isset($capturedMetadata['injected_by_middleware'])) {
            $this->info('[PASS] Context modification verified - both original and injected metadata present');
        } else {
            $this->error('[FAIL] Context modification failed');

            return self::FAILURE;
        }

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
        $this->line('  all       - Run all demos');
        $this->line('  logging   - Test before/after logging middleware');
        $this->line('  isolation - Test middleware isolation between requests');
        $this->line('  ordering  - Test multiple middleware execution order');
        $this->line('  context   - Test context modification via middleware');

        return self::FAILURE;
    }
}
