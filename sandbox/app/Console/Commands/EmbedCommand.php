<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlasphp\Atlas\Atlas;
use Illuminate\Console\Command;
use Prism\Prism\Embeddings\Response as EmbeddingsResponse;

/**
 * Command for testing embedding generation.
 *
 * Demonstrates single and batch embedding capabilities with vector analysis.
 */
class EmbedCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'atlas:embed
                            {text? : Text to embed}
                            {--batch : Enter batch mode for multiple texts}
                            {--file= : Read texts from file (one per line)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test embedding generation with Atlas';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->displayHeader();

        // Handle file input
        if ($file = $this->option('file')) {
            return $this->processFile($file);
        }

        // Handle batch mode
        if ($this->option('batch')) {
            return $this->processBatch();
        }

        // Single text mode
        $text = $this->argument('text');
        if (! $text) {
            $text = $this->ask('Enter text to embed');
        }

        if (! $text) {
            $this->error('No text provided.');

            return self::FAILURE;
        }

        return $this->processSingle($text);
    }

    /**
     * Display the command header.
     */
    protected function displayHeader(): void
    {
        $this->line('');
        $this->line('=== Atlas Embedding Test ===');
        $this->line('Provider: '.config('atlas.embedding.provider', 'openai'));
        $this->line('Model: '.config('atlas.embedding.model', 'text-embedding-3-small'));
        $this->line('');
    }

    /**
     * Process a single text embedding.
     */
    protected function processSingle(string $text): int
    {
        $this->info("Input: \"{$text}\"");
        $this->line('');

        try {
            /** @var EmbeddingsResponse $response */
            $response = Atlas::embeddings()
                ->using(
                    config('atlas.embedding.provider', 'openai'),
                    config('atlas.embedding.model', 'text-embedding-3-small')
                )
                ->fromInput($text)
                ->asEmbeddings();

            // Get the first embedding vector
            $embedding = $response->embeddings[0]->embedding;

            $this->displayEmbeddingAnalysis($embedding);
            $this->displayVerification($embedding);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Process batch embeddings interactively.
     */
    protected function processBatch(): int
    {
        $this->info('Batch mode: Enter texts one per line. Empty line to process.');
        $this->line('');

        $texts = [];
        while (true) {
            $text = $this->ask('Text (empty to process)');

            if ($text === null || $text === '') {
                break;
            }

            $texts[] = $text;
        }

        if (empty($texts)) {
            $this->error('No texts provided.');

            return self::FAILURE;
        }

        $count = count($texts);
        $this->info("Processing {$count} texts...");

        try {
            /** @var EmbeddingsResponse $response */
            $response = Atlas::embeddings()
                ->using(
                    config('atlas.embedding.provider', 'openai'),
                    config('atlas.embedding.model', 'text-embedding-3-small')
                )
                ->fromArray($texts)
                ->asEmbeddings();

            foreach ($response->embeddings as $i => $embeddingObj) {
                $this->line('');
                $this->info("--- Text {$i}: \"{$texts[$i]}\" ---");
                $this->displayEmbeddingAnalysis($embeddingObj->embedding);
            }

            $this->line('');
            $this->info("Batch complete: {$count} embeddings generated.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Process embeddings from a file.
     */
    protected function processFile(string $file): int
    {
        if (! file_exists($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            $this->error("Could not read file: {$file}");

            return self::FAILURE;
        }

        $texts = array_filter(
            array_map('trim', explode("\n", $content)),
            fn ($line) => $line !== ''
        );

        if (empty($texts)) {
            $this->error('File contains no text.');

            return self::FAILURE;
        }

        $count = count($texts);
        $this->info("Processing {$count} texts from file...");

        try {
            /** @var EmbeddingsResponse $response */
            $response = Atlas::embeddings()
                ->using(
                    config('atlas.embedding.provider', 'openai'),
                    config('atlas.embedding.model', 'text-embedding-3-small')
                )
                ->fromArray(array_values($texts))
                ->asEmbeddings();

            foreach ($response->embeddings as $i => $embeddingObj) {
                $textKeys = array_values($texts);
                $text = $textKeys[$i] ?? 'Unknown';
                $this->line('');
                $textPreview = strlen($text) > 50
                    ? substr($text, 0, 50).'...'
                    : $text;
                $this->info("--- Text {$i}: \"{$textPreview}\" ---");
                $this->line('  Dimensions: '.count($embeddingObj->embedding));
            }

            $this->line('');
            $this->info("Batch complete: {$count} embeddings generated.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Display embedding vector analysis.
     *
     * @param  array<int, float|int|string>  $embedding
     */
    protected function displayEmbeddingAnalysis(array $embedding): void
    {
        $count = count($embedding);
        $first5 = array_slice($embedding, 0, 5);
        $last5 = array_slice($embedding, -5);

        // Calculate magnitude
        $sumSquares = array_sum(array_map(fn ($v) => (float) $v * (float) $v, $embedding));
        $magnitude = sqrt($sumSquares);

        // Count non-zero values
        $nonZero = count(array_filter($embedding, fn ($v) => (float) $v !== 0.0));
        $nonZeroPercent = round($nonZero / $count * 100);

        $this->line('Embedding Vector:');
        $this->line("  Dimensions: {$count}");
        $this->line('  First 5 values: ['.implode(', ', array_map(fn ($v) => round((float) $v, 4), $first5)).']');
        $this->line('  Last 5 values: ['.implode(', ', array_map(fn ($v) => round((float) $v, 4), $last5)).']');
        $this->line('  Magnitude: '.round($magnitude, 4));
        $this->line("  Non-zero: {$nonZero} ({$nonZeroPercent}%)");
    }

    /**
     * Display verification results.
     *
     * @param  array<int, float|int|string>  $embedding
     */
    protected function displayVerification(array $embedding): void
    {
        $count = count($embedding);

        // Calculate magnitude
        $sumSquares = array_sum(array_map(fn ($v) => (float) $v * (float) $v, $embedding));
        $magnitude = sqrt($sumSquares);

        // Check values in range
        $inRange = count(array_filter($embedding, fn ($v) => (float) $v >= -1 && (float) $v <= 1));
        $allInRange = $inRange === $count;

        $this->line('');
        $this->line('--- Verification ---');

        // Dimension check
        $this->info("[PASS] Vector has {$count} dimensions");

        // Normalization check (magnitude should be ~1.0 for normalized vectors)
        if ($magnitude >= 0.99 && $magnitude <= 1.01) {
            $this->info('[PASS] Vector is normalized (magnitude ~1.0)');
        } else {
            $this->warn('[WARN] Vector magnitude is '.round($magnitude, 4).' (expected ~1.0)');
        }

        // Range check
        if ($allInRange) {
            $this->info('[PASS] Values are within expected range [-1, 1]');
        } else {
            $outOfRange = $count - $inRange;
            $this->error("[FAIL] {$outOfRange} values are outside range [-1, 1]");
        }
    }
}
