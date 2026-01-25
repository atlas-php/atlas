<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlasphp\Atlas\Atlas;
use Illuminate\Console\Command;
use Prism\Prism\Moderation\Response as ModerationResponse;
use Prism\Prism\ValueObjects\ModerationResult;

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

        /** @var ModerationResponse $response */
        $response = Atlas::moderation()
            ->using(
                config('atlas.moderation.provider', 'openai'),
                config('atlas.moderation.model', 'omni-moderation-latest')
            )
            ->withInput($text)
            ->asModeration();

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

        /** @var ModerationResponse $response */
        $response = Atlas::moderation()
            ->using(
                config('atlas.moderation.provider', 'openai'),
                config('atlas.moderation.model', 'omni-moderation-latest')
            )
            ->withInput($testCases)
            ->asModeration();

        foreach ($response->results as $i => $result) {
            $text = $testCases[$i] ?? 'Unknown';
            $this->line('--- Text '.($i + 1).": \"{$text}\" ---");
            $this->displaySingleResult($result);
            $this->line('');
        }

        $this->line('Response Details:');
        $this->line('  Total Results: '.count($response->results));
        $this->line('  Any Flagged: '.($response->isFlagged() ? 'Yes' : 'No'));

        return self::SUCCESS;
    }

    /**
     * Display a single moderation result.
     */
    protected function displaySingleResult(ModerationResult $result): void
    {
        if ($result->flagged) {
            $this->error('[FLAGGED]');
        } else {
            $this->info('[SAFE]');
        }

        $flaggedCategories = array_keys(array_filter($result->categories));
        if (! empty($flaggedCategories)) {
            $this->line('  Flagged categories: '.implode(', ', $flaggedCategories));
        }
    }

    /**
     * Display moderation result.
     */
    protected function displayResult(ModerationResponse $response): void
    {
        // Overall status
        if ($response->isFlagged()) {
            $this->error('[FLAGGED] Content was flagged for moderation');
        } else {
            $this->info('[SAFE] Content passed moderation');
        }

        $this->line('');

        // Get first result for single text moderation
        $result = $response->results[0] ?? null;
        if ($result === null) {
            $this->warn('No moderation result returned.');

            return;
        }

        // Show categories if any
        if (! empty($result->categories)) {
            $this->line('Categories:');

            $tableData = [];
            foreach ($result->categories as $category => $isFlagged) {
                $score = $result->categoryScores[$category] ?? 0;
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
        $this->line('  Results: '.count($response->results));
    }
}
