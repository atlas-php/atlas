<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlasphp\Atlas\Atlas;
use Illuminate\Console\Command;

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
                            {--file= : Read texts from file (one per line)}
                            {--dimensions : Show configured embedding dimensions}';

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
        // Show dimensions only
        if ($this->option('dimensions')) {
            $dimensions = Atlas::embeddings()->dimensions();
            $this->info("Configured embedding dimensions: {$dimensions}");

            return self::SUCCESS;
        }

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
        $this->line('Dimensions: '.Atlas::embeddings()->dimensions());
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
            $embedding = Atlas::embeddings()->generate($text);

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
            $embeddings = Atlas::embeddings()->generate($texts);

            foreach ($embeddings as $i => $embedding) {
                $this->line('');
                $this->info("--- Text {$i}: \"{$texts[$i]}\" ---");
                $this->displayEmbeddingAnalysis($embedding);
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
            $embeddings = Atlas::embeddings()->generate($texts);

            foreach ($embeddings as $i => $embedding) {
                $this->line('');
                $textPreview = strlen($texts[$i]) > 50
                    ? substr($texts[$i], 0, 50).'...'
                    : $texts[$i];
                $this->info("--- Text {$i}: \"{$textPreview}\" ---");
                $this->line('  Dimensions: '.count($embedding));
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
     * @param  array<int, float>  $embedding
     */
    protected function displayEmbeddingAnalysis(array $embedding): void
    {
        $count = count($embedding);
        $first5 = array_slice($embedding, 0, 5);
        $last5 = array_slice($embedding, -5);

        // Calculate magnitude
        $sumSquares = array_sum(array_map(fn ($v) => $v * $v, $embedding));
        $magnitude = sqrt($sumSquares);

        // Count non-zero values
        $nonZero = count(array_filter($embedding, fn ($v) => $v !== 0.0));
        $nonZeroPercent = round($nonZero / $count * 100);

        $this->line('Embedding Vector:');
        $this->line("  Dimensions: {$count}");
        $this->line('  First 5 values: ['.implode(', ', array_map(fn ($v) => round($v, 4), $first5)).']');
        $this->line('  Last 5 values: ['.implode(', ', array_map(fn ($v) => round($v, 4), $last5)).']');
        $this->line('  Magnitude: '.round($magnitude, 4));
        $this->line("  Non-zero: {$nonZero} ({$nonZeroPercent}%)");
    }

    /**
     * Display verification results.
     *
     * @param  array<int, float>  $embedding
     */
    protected function displayVerification(array $embedding): void
    {
        $expectedDimensions = Atlas::embeddings()->dimensions();
        $count = count($embedding);

        // Calculate magnitude
        $sumSquares = array_sum(array_map(fn ($v) => $v * $v, $embedding));
        $magnitude = sqrt($sumSquares);

        // Check values in range
        $inRange = count(array_filter($embedding, fn ($v) => $v >= -1 && $v <= 1));
        $allInRange = $inRange === $count;

        $this->line('');
        $this->line('--- Verification ---');

        // Dimension check
        if ($count === $expectedDimensions) {
            $this->info("[PASS] Vector has expected dimensions ({$expectedDimensions})");
        } else {
            $this->error("[FAIL] Vector has {$count} dimensions, expected {$expectedDimensions}");
        }

        // Normalization check (magnitude should be ~1.0 for normalized vectors)
        if ($magnitude >= 0.99 && $magnitude <= 1.01) {
            $this->info('[PASS] Vector is normalized (magnitude ~1.0)');
        } else {
            $this->warn("[WARN] Vector magnitude is {$magnitude} (expected ~1.0)");
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
