<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlasphp\Atlas\Atlas;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

/**
 * Interactive chat command for testing with local LLMs.
 *
 * Connects to OpenAI-compatible local LLM servers like LM Studio, Ollama,
 * or any other server that provides an OpenAI-compatible API.
 *
 * Uses Atlas's agent system with runtime config modification to point
 * the OpenAI provider to the local server.
 */
class LocalChatCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'atlas:local-chat
                            {--url= : The local LLM server URL (default: OLLAMA_URL env)}
                            {--model= : The model to use (default: OLLAMA_MODEL env)}
                            {--system= : Custom system prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interactive chat with a local LLM via Atlas (LM Studio, Ollama, etc.)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $url = $this->option('url') ?? env('OLLAMA_URL', 'http://localhost:11434/v1');
        $model = $this->option('model') ?? env('OLLAMA_MODEL', 'llama3');
        $systemPrompt = $this->option('system');

        // Display header
        $this->displayHeader($url, $model);

        // Verify connection
        if (! $this->verifyConnection($url)) {
            return self::FAILURE;
        }

        // Configure Prism to use the local URL for OpenAI provider
        $this->configureLocalProvider($url);

        /** @var array<int, array{role: string, content: string}> $messages */
        $messages = [];

        // Enter REPL loop
        while (true) {
            $input = $this->ask('You');

            if ($input === null || $input === '') {
                continue;
            }

            // Handle special commands
            $command = strtolower(trim($input));

            if ($command === 'exit' || $command === 'quit') {
                $this->info('Goodbye!');

                return self::SUCCESS;
            }

            if ($command === 'clear') {
                $messages = [];
                $this->info('Conversation cleared.');

                continue;
            }

            if ($command === 'history') {
                $this->displayHistory($messages);

                continue;
            }

            // Send message to local LLM via Atlas
            try {
                $request = Atlas::agent('local-l-m')
                    ->withModel($model)
                    ->withMessages($messages);

                // Apply custom system prompt if provided
                if ($systemPrompt !== null) {
                    // System prompt is set via agent, so we'd need a different approach
                    // For now, just use the agent's default
                }

                $response = $request->chat($input);

                $text = $response->text ?? '[No response]';

                // Add both messages to history for next turn
                $messages[] = ['role' => 'user', 'content' => $input];
                $messages[] = ['role' => 'assistant', 'content' => $text];

                // Display response
                $this->line('');
                $this->info("Assistant> {$text}");
                $this->line('');

                // Display response details
                $this->displayResponseDetails($response);

            } catch (\Throwable $e) {
                $this->error("Error: {$e->getMessage()}");
                // Remove failed user message
                array_pop($messages);
            }
        }
    }

    /**
     * Configure Prism to use the local LLM URL.
     *
     * Modifies the runtime config so the OpenAI provider points to the local server.
     */
    protected function configureLocalProvider(string $url): void
    {
        // Set the OpenAI URL to the local server at runtime
        Config::set('prism.providers.openai.url', $url);
        Config::set('prism.providers.openai.api_key', 'not-needed');
    }

    /**
     * Display the chat header.
     */
    protected function displayHeader(string $url, string $model): void
    {
        $this->line('');
        $this->line('=== Atlas Local LLM Chat ===');
        $this->line("URL: {$url}");
        $this->line("Model: {$model}");
        $this->line('Commands: exit, clear, history');
        $this->line('');
    }

    /**
     * Verify connection to the local LLM server.
     */
    protected function verifyConnection(string $url): bool
    {
        $this->info("Connecting to {$url}...");

        try {
            // Try a simple models endpoint check
            $client = new \GuzzleHttp\Client(['timeout' => 5]);
            $modelsUrl = rtrim($url, '/').'/models';
            $response = $client->get($modelsUrl);

            if ($response->getStatusCode() === 200) {
                $this->info('Connected successfully!');
                $this->line('');

                return true;
            }
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            $this->error("Failed to connect to {$url}");
            $this->error('Make sure your local LLM server is running.');

            return false;
        } catch (\Throwable $e) {
            // Connection worked but endpoint may not exist - that's fine
            $this->info('Connected (models endpoint not available, but server is reachable).');
            $this->line('');

            return true;
        }

        return true;
    }

    /**
     * Display conversation history.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    protected function displayHistory(array $messages): void
    {
        if (empty($messages)) {
            $this->info('No messages yet.');

            return;
        }

        $this->line('');
        $this->info('=== Conversation History ===');

        foreach ($messages as $i => $message) {
            $role = ucfirst($message['role']);
            $content = $message['content'];
            // Truncate long messages for display
            if (strlen($content) > 200) {
                $content = substr($content, 0, 200).'...';
            }
            $this->line("[{$i}] {$role}: {$content}");
        }

        $this->line('');
    }

    /**
     * Display response details.
     *
     * @param  \Prism\Prism\Text\Response  $response
     */
    protected function displayResponseDetails($response): void
    {
        $this->line('--- Response Details ---');

        $this->line(sprintf(
            'Tokens: %d prompt / %d completion / %d total',
            $response->usage->promptTokens,
            $response->usage->completionTokens,
            $response->usage->promptTokens + $response->usage->completionTokens,
        ));

        $finishReason = $response->finishReason->value ?? 'unknown';
        $this->line("Finish: {$finishReason}");

        $toolCallCount = count($response->toolCalls);
        $this->line("Tool Calls: {$toolCallCount}");

        $this->line('------------------------');
        $this->line('');
    }
}
