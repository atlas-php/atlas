<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlasphp\Atlas\Atlas;
use Illuminate\Console\Command;

/**
 * Command for testing image generation.
 *
 * Demonstrates image generation with size and quality options.
 */
class ImageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'atlas:image
                            {prompt : The image generation prompt}
                            {--size=1024x1024 : Image dimensions}
                            {--quality=standard : Quality level (standard|hd)}
                            {--style=vivid : Style (vivid|natural)}
                            {--save= : Save to filename in storage/outputs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test image generation with Atlas';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $prompt = $this->argument('prompt');
        $size = $this->option('size');
        $quality = $this->option('quality');
        $style = $this->option('style');
        $saveAs = $this->option('save');

        $this->displayHeader($prompt, $size, $quality, $style);

        try {
            $this->info('Generating image...');

            $response = Atlas::image()
                ->withSize($size)
                ->withQuality($quality)
                ->withProviderOptions(['style' => $style])
                ->generate($prompt);

            $this->displayResponse($response, $saveAs);
            $this->displayVerification($response);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Display the command header.
     */
    protected function displayHeader(string $prompt, string $size, string $quality, string $style): void
    {
        $this->line('');
        $this->line('=== Atlas Image Generation Test ===');
        $this->line('Provider: '.config('atlas.image.provider', 'openai'));
        $this->line('Model: '.config('atlas.image.model', 'dall-e-3'));
        $this->line('');
        $this->line("Prompt: \"{$prompt}\"");
        $this->line("Size: {$size}");
        $this->line("Quality: {$quality}");
        $this->line("Style: {$style}");
        $this->line('');
    }

    /**
     * Display the response.
     *
     * @param  mixed  $response
     */
    protected function displayResponse($response, ?string $saveAs): void
    {
        $this->line('--- Response ---');

        // Handle different response structures
        $url = $this->extractUrl($response);
        $revisedPrompt = $this->extractRevisedPrompt($response);
        $base64 = $this->extractBase64($response);

        if ($url) {
            $this->line("URL: {$url}");
        }

        if ($revisedPrompt) {
            $this->line("Revised Prompt: \"{$revisedPrompt}\"");
        }

        if ($base64) {
            $size = strlen($base64);
            $sizeKb = round($size / 1024, 1);
            $this->line("Base64: [available - {$sizeKb}KB]");

            if ($saveAs) {
                $this->saveImage($base64, $saveAs);
            }
        }

        $this->line('');
    }

    /**
     * Display verification results.
     *
     * @param  mixed  $response
     */
    protected function displayVerification($response): void
    {
        $this->line('--- Verification ---');

        $url = $this->extractUrl($response);
        $revisedPrompt = $this->extractRevisedPrompt($response);
        $base64 = $this->extractBase64($response);

        if ($url) {
            $this->info('[PASS] Response contains valid URL');
        } else {
            $this->warn('[WARN] Response does not contain URL');
        }

        if ($revisedPrompt) {
            $this->info('[PASS] Response contains revised prompt');
        } else {
            $this->warn('[WARN] Response does not contain revised prompt');
        }

        if ($base64) {
            // Check if it's valid base64
            $decoded = base64_decode($base64, true);
            if ($decoded !== false && strlen($decoded) > 0) {
                $this->info('[PASS] Base64 data is valid image format');
            } else {
                $this->error('[FAIL] Base64 data is invalid');
            }
        }
    }

    /**
     * Save base64 image to file.
     */
    protected function saveImage(string $base64, string $filename): void
    {
        $decoded = base64_decode($base64, true);
        if ($decoded === false) {
            $this->error('Failed to decode base64 data');

            return;
        }

        // Ensure .png extension
        if (! str_ends_with($filename, '.png')) {
            $filename .= '.png';
        }

        $path = dirname(__DIR__, 3).'/storage/outputs/'.$filename;
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_put_contents($path, $decoded) !== false) {
            $this->info("Saved to: {$path}");
        } else {
            $this->error("Failed to save to: {$path}");
        }
    }

    /**
     * Extract URL from response.
     *
     * @param  mixed  $response
     */
    protected function extractUrl($response): ?string
    {
        if (is_object($response)) {
            if (isset($response->url)) {
                return $response->url;
            }
            if (method_exists($response, 'url')) {
                return $response->url();
            }
        }

        if (is_array($response) && isset($response['url'])) {
            return $response['url'];
        }

        return null;
    }

    /**
     * Extract revised prompt from response.
     *
     * @param  mixed  $response
     */
    protected function extractRevisedPrompt($response): ?string
    {
        if (is_object($response)) {
            if (isset($response->revisedPrompt)) {
                return $response->revisedPrompt;
            }
            if (isset($response->revised_prompt)) {
                return $response->revised_prompt;
            }
            if (method_exists($response, 'revisedPrompt')) {
                return $response->revisedPrompt();
            }
        }

        if (is_array($response)) {
            return $response['revised_prompt'] ?? $response['revisedPrompt'] ?? null;
        }

        return null;
    }

    /**
     * Extract base64 data from response.
     *
     * @param  mixed  $response
     */
    protected function extractBase64($response): ?string
    {
        if (is_object($response)) {
            if (isset($response->base64)) {
                return $response->base64;
            }
            if (isset($response->b64_json)) {
                return $response->b64_json;
            }
            if (method_exists($response, 'base64')) {
                return $response->base64();
            }
        }

        if (is_array($response)) {
            return $response['base64'] ?? $response['b64_json'] ?? null;
        }

        return null;
    }
}
