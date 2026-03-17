<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlasphp\Atlas\Atlas;
use Illuminate\Console\Command;
use Prism\Prism\Embeddings\Response as EmbeddingsResponse;

/**
 * Command for testing embedding generation.
 *
 * Demonstrates single, batch, and cached embedding capabilities with vector analysis.
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
                            {--cache : Enable caching for this request}
                            {--cache-demo : Run the cache demo (two identical calls, second should be cached)}';

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

        // Handle cache demo
        if ($this->option('cache-demo')) {
            return $this->processCacheDemo();
        }

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
        $this->line('Provider: '.config('atlas.embeddings.provider', 'openai'));
        $this->line('Model: '.config('atlas.embeddings.model', 'text-embedding-3-small'));

        $cacheEnabled = config('atlas.embeddings.cache.enabled', false);
        $this->line('Cache: '.($cacheEnabled ? 'enabled (global)' : 'disabled (global)'));

        if ($this->option('cache')) {
            $this->line('Cache: enabled (per-request override)');
        }

        $this->line('');
    }

    /**
     * Build metadata array for the current request.
     *
     * @return array<string, mixed>
     */
    protected function buildMetadata(): array
    {
        $metadata = [];

        if ($this->option('cache')) {
            $metadata['cache'] = true;
        }

        return $metadata;
    }

    /**
     * Process a single text embedding.
     */
    protected function processSingle(string $text): int
    {
        $this->info("Input: \"{$text}\"");
        $this->line('');

        try {
            $metadata = $this->buildMetadata();

            $request = Atlas::embeddings()
                ->fromInput($text);

            if ($metadata !== []) {
                $request = $request->withMetadata($metadata);
            }

            /** @var EmbeddingsResponse $response */
            $response = $request->asEmbeddings();

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
            $metadata = $this->buildMetadata();

            $request = Atlas::embeddings()
                ->fromArray($texts);

            if ($metadata !== []) {
                $request = $request->withMetadata($metadata);
            }

            /** @var EmbeddingsResponse $response */
            $response = $request->asEmbeddings();

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
            $metadata = $this->buildMetadata();

            $request = Atlas::embeddings()
                ->fromArray(array_values($texts));

            if ($metadata !== []) {
                $request = $request->withMetadata($metadata);
            }

            /** @var EmbeddingsResponse $response */
            $response = $request->asEmbeddings();

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
     * Run the cache demo: two identical calls, second should be cached.
     */
    protected function processCacheDemo(): int
    {
        $text = $this->argument('text') ?? 'Atlas embedding cache test';

        $this->info('--- Cache Demo ---');
        $this->line("Text: \"{$text}\"");
        $this->line('');

        try {
            // First call — should hit the API
            $this->info('Call 1: Requesting embedding (should hit API)...');
            $start = microtime(true);

            /** @var EmbeddingsResponse $response1 */
            $response1 = Atlas::embeddings()
                ->withMetadata(['cache' => true])
                ->fromInput($text)
                ->asEmbeddings();

            $duration1 = round((microtime(true) - $start) * 1000);
            $dims1 = count($response1->embeddings[0]->embedding);
            $this->line("  Result: {$dims1} dimensions, {$duration1}ms");

            // Second call — should return cached
            $this->info('Call 2: Requesting same embedding (should be cached)...');
            $start = microtime(true);

            /** @var EmbeddingsResponse $response2 */
            $response2 = Atlas::embeddings()
                ->withMetadata(['cache' => true])
                ->fromInput($text)
                ->asEmbeddings();

            $duration2 = round((microtime(true) - $start) * 1000);
            $dims2 = count($response2->embeddings[0]->embedding);
            $this->line("  Result: {$dims2} dimensions, {$duration2}ms");

            // Third call — cache disabled, should hit API
            $this->info('Call 3: Requesting same embedding with cache disabled...');
            $start = microtime(true);

            /** @var EmbeddingsResponse $response3 */
            $response3 = Atlas::embeddings()
                ->withMetadata(['cache' => false])
                ->fromInput($text)
                ->asEmbeddings();

            $duration3 = round((microtime(true) - $start) * 1000);
            $dims3 = count($response3->embeddings[0]->embedding);
            $this->line("  Result: {$dims3} dimensions, {$duration3}ms");

            // Verification
            $this->line('');
            $this->line('--- Verification ---');

            // Check vectors match
            $vec1 = $response1->embeddings[0]->embedding;
            $vec2 = $response2->embeddings[0]->embedding;
            if ($vec1 === $vec2) {
                $this->info('[PASS] Call 1 and Call 2 returned identical vectors (cache hit confirmed)');
            } else {
                $this->warn('[WARN] Call 1 and Call 2 returned different vectors');
            }

            // Check timing — cached call should be faster
            if ($duration2 < $duration1) {
                $speedup = $duration1 > 0 ? round($duration1 / max($duration2, 1)) : 0;
                $this->info("[PASS] Call 2 was {$speedup}x faster ({$duration2}ms vs {$duration1}ms)");
            } else {
                $this->warn("[WARN] Call 2 was not faster ({$duration2}ms vs {$duration1}ms)");
            }

            // Check that call 3 still works
            $dims3Count = count($response3->embeddings[0]->embedding);
            if ($dims3Count === $dims1) {
                $this->info("[PASS] Call 3 (cache disabled) returned valid {$dims3Count}-dim vector");
            } else {
                $this->warn("[WARN] Call 3 dimension mismatch: {$dims3Count} vs {$dims1}");
            }

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
