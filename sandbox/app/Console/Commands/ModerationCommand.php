<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlasphp\Atlas\Atlas;
use Illuminate\Console\Command;

/**
 * Command for testing content moderation.
 *
 * Demonstrates content moderation with category detection and scoring.
 */
class ModerationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'atlas:moderation
                            {text? : The text to moderate}
                            {--batch : Run with test batch of different content types}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test content moderation with Atlas';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->displayHeader();

        try {
            if ($this->option('batch')) {
                return $this->runBatchTest();
            }

            $text = $this->argument('text') ?? 'Hello, how are you today?';

            return $this->moderateText($text);
        } catch (\Throwable $e) {
            $this->error("Error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Display the command header.
     */
    protected function displayHeader(): void
    {
        $this->line('');
        $this->line('=== Atlas Content Moderation Test ===');
        $this->line('Provider: '.config('atlas.moderation.provider', 'openai'));
        $this->line('Model: '.config('atlas.moderation.model', 'omni-moderation-latest'));
        $this->line('');
    }

    /**
     * Moderate a single text.
     */
    protected function moderateText(string $text): int
    {
        $this->line("Text: \"{$text}\"");
        $this->line('');
        $this->info('Analyzing content...');
        $this->line('');

        $response = Atlas::moderation()->moderate($text);

        $this->displayResult($response);

        return self::SUCCESS;
    }

    /**
     * Run batch test with various content types.
     */
    protected function runBatchTest(): int
    {
        $testCases = [
            'Hello, how are you today?',
            'What is the capital of France?',
            'How do I implement a binary search algorithm?',
        ];

        $this->line('Batch moderating '.count($testCases).' texts:');
        foreach ($testCases as $i => $text) {
            $this->line('  '.($i + 1).'. "'.$text.'"');
        }
        $this->line('');
        $this->info('Analyzing content...');
        $this->line('');

        $response = Atlas::moderation()->moderate($testCases);

        foreach ($response->results as $i => $result) {
            $text = $testCases[$i] ?? 'Unknown';
            $this->line('--- Text '.($i + 1).": \"{$text}\" ---");
            $this->displaySingleResult($result);
            $this->line('');
        }

        $this->line('Response Details:');
        $this->line("  ID: {$response->id}");
        $this->line("  Model: {$response->model}");
        $this->line('  Total Results: '.count($response->results));
        $this->line('  Flagged: '.($response->isFlagged() ? 'Yes' : 'No'));

        return self::SUCCESS;
    }

    /**
     * Display a single moderation result.
     *
     * @param  \Atlasphp\Atlas\Providers\Support\ModerationResult  $result
     */
    protected function displaySingleResult($result): void
    {
        if ($result->flagged) {
            $this->error('[FLAGGED]');
        } else {
            $this->info('[SAFE]');
        }

        $flaggedCategories = $result->flaggedCategories();
        if (! empty($flaggedCategories)) {
            $this->line('  Flagged categories: '.implode(', ', $flaggedCategories));
        }
    }

    /**
     * Display moderation result.
     *
     * @param  \Atlasphp\Atlas\Providers\Support\ModerationResponse  $response
     */
    protected function displayResult($response): void
    {
        // Overall status
        if ($response->isFlagged()) {
            $this->error('[FLAGGED] Content was flagged for moderation');
        } else {
            $this->info('[SAFE] Content passed moderation');
        }

        $this->line('');

        // Show flagged categories if any
        $categories = $response->categories();
        $scores = $response->categoryScores();

        if (! empty($categories)) {
            $this->line('Categories:');

            $tableData = [];
            foreach ($categories as $category => $isFlagged) {
                $score = $scores[$category] ?? 0;
                $status = $isFlagged ? '<fg=red>FLAGGED</>' : '<fg=green>OK</>';
                $tableData[] = [
                    $category,
                    $status,
                    number_format($score * 100, 2).'%',
                ];
            }

            $this->table(['Category', 'Status', 'Score'], $tableData);
        }

        // Show API response details
        $this->line('');
        $this->line('Response Details:');
        $this->line("  ID: {$response->id}");
        $this->line("  Model: {$response->model}");
        $this->line('  Results: '.count($response->results));
    }
}
