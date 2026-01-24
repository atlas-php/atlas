<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlasphp\Atlas\Atlas;
use Illuminate\Console\Command;
use Prism\Prism\Images\Response as ImageResponse;

/**
 * Command for testing image generation.
 *
 * Demonstrates image generation with size and quality options via provider options.
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

            /** @var ImageResponse $response */
            $response = Atlas::image()
                ->using(
                    config('atlas.image.provider', 'openai'),
                    config('atlas.image.model', 'dall-e-3')
                )
                ->withPrompt($prompt)
                ->withProviderOptions([
                    'size' => $size,
                    'quality' => $quality,
                    'style' => $style,
                ])
                ->generate();

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
     */
    protected function displayResponse(ImageResponse $response, ?string $saveAs): void
    {
        $this->line('--- Response ---');

        $image = $response->firstImage();

        if ($image === null) {
            $this->warn('No image generated.');

            return;
        }

        if ($image->url !== null) {
            $this->line("URL: {$image->url}");
        }

        if ($image->revisedPrompt !== null) {
            $this->line("Revised Prompt: \"{$image->revisedPrompt}\"");
        }

        if ($image->base64 !== null) {
            $size = strlen($image->base64);
            $sizeKb = round($size / 1024, 1);
            $this->line("Base64: [available - {$sizeKb}KB]");

            if ($saveAs) {
                $this->saveImage($image->base64, $saveAs);
            }
        }

        $this->line('');
    }

    /**
     * Display verification results.
     */
    protected function displayVerification(ImageResponse $response): void
    {
        $this->line('--- Verification ---');

        $image = $response->firstImage();

        if ($image === null) {
            $this->error('[FAIL] No image in response');

            return;
        }

        if ($image->url !== null) {
            $this->info('[PASS] Response contains valid URL');
        } else {
            $this->warn('[WARN] Response does not contain URL');
        }

        if ($image->revisedPrompt !== null) {
            $this->info('[PASS] Response contains revised prompt');
        } else {
            $this->warn('[WARN] Response does not contain revised prompt');
        }

        if ($image->base64 !== null) {
            // Check if it's valid base64
            $decoded = base64_decode($image->base64, true);
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
}
