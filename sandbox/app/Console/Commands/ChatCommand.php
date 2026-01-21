<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ThreadStorageService;
use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use Atlasphp\Atlas\Providers\Facades\Atlas;
use Illuminate\Console\Command;

/**
 * Interactive chat command for testing agent conversations.
 *
 * Provides a REPL interface for chatting with agents and persists
 * conversation threads to the filesystem.
 */
class ChatCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'atlas:chat
                            {agent? : The agent key to use (default: general-assistant)}
                            {--thread= : Continue an existing thread by UUID}
                            {--new : Force creating a new thread}
                            {--list : List all saved threads}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interactive chat with an Atlas agent';

    /**
     * Execute the console command.
     */
    public function handle(
        ThreadStorageService $storage,
        AgentRegistryContract $agentRegistry,
    ): int {
        // Handle --list option
        if ($this->option('list')) {
            return $this->listThreads($storage);
        }

        $agentKey = $this->argument('agent') ?? 'general-assistant';

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

        // Load or create thread
        $thread = $this->resolveThread($storage, $agentKey);
        if ($thread === null) {
            return self::FAILURE;
        }

        // Display header
        $this->displayHeader($agentKey, $agent->provider(), $agent->model(), $thread['uuid']);

        // Enter REPL loop
        return $this->replLoop($storage, $thread, $agentKey);
    }

    /**
     * List all saved threads.
     */
    protected function listThreads(ThreadStorageService $storage): int
    {
        $threads = $storage->list();

        if (empty($threads)) {
            $this->info('No saved threads found.');

            return self::SUCCESS;
        }

        $this->info('Saved Threads:');
        $this->line('');

        $headers = ['UUID', 'Agent', 'Messages', 'Last Updated'];
        $rows = [];

        foreach ($threads as $thread) {
            $rows[] = [
                $thread['uuid'],
                $thread['agent'],
                $thread['message_count'],
                $thread['updated_at'],
            ];
        }

        $this->table($headers, $rows);

        return self::SUCCESS;
    }

    /**
     * Resolve the thread to use (load existing or create new).
     *
     * @return array{uuid: string, agent: string, created_at: string, updated_at: string, messages: array<int, array{role: string, content: string}>, metadata: array{total_tokens: int, message_count: int}}|null
     */
    protected function resolveThread(ThreadStorageService $storage, string $agentKey): ?array
    {
        $threadUuid = $this->option('thread');
        $forceNew = $this->option('new');

        if ($threadUuid && ! $forceNew) {
            $thread = $storage->load($threadUuid);
            if ($thread === null) {
                $this->error("Thread not found: {$threadUuid}");

                return null;
            }
            $this->info("Continuing thread: {$threadUuid}");

            return $thread;
        }

        $thread = $storage->create($agentKey);
        $this->info("Created new thread: {$thread['uuid']}");

        return $thread;
    }

    /**
     * Display the chat header.
     */
    protected function displayHeader(string $agentKey, string $provider, string $model, string $uuid): void
    {
        $this->line('');
        $this->line('=== Atlas Chat Sandbox ===');
        $this->line("Agent: {$agentKey} ({$provider}/{$model})");
        $this->line("Thread: {$uuid}");
        $this->line('Commands: exit, clear, history, save');
        $this->line('');
    }

    /**
     * Run the REPL loop.
     *
     * @param  array{uuid: string, agent: string, created_at: string, updated_at: string, messages: array<int, array{role: string, content: string}>, metadata: array{total_tokens: int, message_count: int}}  $thread
     */
    protected function replLoop(ThreadStorageService $storage, array $thread, string $agentKey): int
    {
        while (true) {
            $input = $this->ask('You');

            if ($input === null || $input === '') {
                continue;
            }

            // Handle special commands
            $command = strtolower(trim($input));

            if ($command === 'exit' || $command === 'quit') {
                $storage->save($thread);
                $this->info('Thread saved. Goodbye!');

                return self::SUCCESS;
            }

            if ($command === 'clear') {
                $thread = $storage->clearMessages($thread);
                $storage->save($thread);
                $this->info('Thread cleared.');

                continue;
            }

            if ($command === 'history') {
                $this->displayHistory($thread);

                continue;
            }

            if ($command === 'save') {
                $storage->save($thread);
                $this->info('Thread saved.');

                continue;
            }

            // Send message to agent
            try {
                $thread = $storage->addMessage($thread, 'user', $input);

                $response = Atlas::forMessages($thread['messages'])
                    ->chat($agentKey, $input);

                $text = $response->text ?? '[No response]';
                $thread = $storage->addMessage($thread, 'assistant', $text);
                $thread = $storage->addTokens($thread, $response->totalTokens());
                $storage->save($thread);

                // Display response
                $this->line('');
                $this->info("Assistant> {$text}");
                $this->line('');

                // Display response details
                $this->displayResponseDetails($response);

            } catch (\Throwable $e) {
                $this->error("Error: {$e->getMessage()}");
            }
        }
    }

    /**
     * Display conversation history.
     *
     * @param  array{uuid: string, agent: string, created_at: string, updated_at: string, messages: array<int, array{role: string, content: string}>, metadata: array{total_tokens: int, message_count: int}}  $thread
     */
    protected function displayHistory(array $thread): void
    {
        if (empty($thread['messages'])) {
            $this->info('No messages in this thread yet.');

            return;
        }

        $this->line('');
        $this->info('=== Conversation History ===');

        foreach ($thread['messages'] as $i => $message) {
            $role = ucfirst($message['role']);
            $this->line("[{$i}] {$role}: {$message['content']}");
        }

        $this->line('');
        $this->line("Total tokens used: {$thread['metadata']['total_tokens']}");
        $this->line('');
    }

    /**
     * Display response details.
     *
     * @param  \Atlasphp\Atlas\Agents\Support\AgentResponse  $response
     */
    protected function displayResponseDetails($response): void
    {
        $this->line('--- Response Details ---');
        $this->line(sprintf(
            'Tokens: %d prompt / %d completion / %d total',
            $response->promptTokens(),
            $response->completionTokens(),
            $response->totalTokens(),
        ));

        $finishReason = $response->get('finish_reason', 'unknown');
        $this->line("Finish: {$finishReason}");

        $toolCallCount = count($response->toolCalls);
        $this->line("Tool Calls: {$toolCallCount}");

        $this->line('------------------------');
        $this->line('');
    }
}
