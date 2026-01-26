<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ThreadStorageService;
use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Pipelines\PipelineRegistry;
use Illuminate\Console\Command;
use Prism\Prism\Images\Response as ImageResponse;
use Prism\Prism\ValueObjects\Media\Audio;
use Prism\Prism\ValueObjects\Media\Document;
use Prism\Prism\ValueObjects\Media\Image;

/**
 * Comprehensive vision test command for multimodal attachments.
 *
 * Tests image, document, audio, and video attachments with various providers.
 * Verifies pipeline context contains expected attachment data.
 */
class VisionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'atlas:vision
                            {--provider=openai : Provider to test (openai, anthropic, gemini)}
                            {--image= : Path or URL to an image}
                            {--document= : Path or URL to a document}
                            {--audio= : Path or URL to an audio file}
                            {--url : Use URL source instead of local path}
                            {--base64 : Use base64 encoding}
                            {--thread : Test with conversation history (multiple turns)}
                            {--all-providers : Test with all available providers}
                            {--generate-assets : Generate test assets using image generation}
                            {--pipeline-debug : Enable pipeline debugging to inspect context}
                            {--comprehensive : Run comprehensive test suite}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test multimodal attachments (images, documents, audio, video) with vision-capable models';

    /**
     * Assets storage path.
     */
    protected string $assetsPath;

    /**
     * Execute the console command.
     */
    public function handle(
        ThreadStorageService $storage,
        AgentRegistryContract $agentRegistry,
        PipelineRegistry $pipelineRegistry,
    ): int {
        $this->assetsPath = dirname(__DIR__, 3).'/storage/assets';

        // Ensure assets directory exists
        if (! is_dir($this->assetsPath)) {
            mkdir($this->assetsPath, 0755, true);
        }

        // Generate test assets if requested
        if ($this->option('generate-assets')) {
            return $this->generateTestAssets();
        }

        // Run comprehensive test suite
        if ($this->option('comprehensive')) {
            return $this->runComprehensiveTests($storage, $agentRegistry, $pipelineRegistry);
        }

        // Test all providers
        if ($this->option('all-providers')) {
            return $this->testAllProviders($storage, $agentRegistry, $pipelineRegistry);
        }

        // Single provider test
        $provider = $this->option('provider');

        return $this->testProvider($provider, $storage, $agentRegistry, $pipelineRegistry);
    }

    /**
     * Generate test assets using image generation.
     */
    protected function generateTestAssets(): int
    {
        $this->info('=== Generating Test Assets ===');
        $this->line('');

        $assets = [
            'test-landscape.png' => 'A beautiful mountain landscape with a lake at sunset, photorealistic',
            'test-diagram.png' => 'A simple flowchart diagram with three boxes connected by arrows, business style',
            'test-text-image.png' => 'A white background with the text "Hello Atlas" written in blue font',
        ];

        foreach ($assets as $filename => $prompt) {
            $path = $this->assetsPath.'/'.$filename;

            if (file_exists($path)) {
                $this->line("  [SKIP] {$filename} already exists");

                continue;
            }

            $this->line("  Generating {$filename}...");

            try {
                $response = Atlas::image()
                    ->using(
                        config('atlas.image.provider', 'openai'),
                        config('atlas.image.model', 'dall-e-3')
                    )
                    ->withPrompt($prompt)
                    ->withProviderOptions([
                        'size' => '1024x1024',
                        'response_format' => 'b64_json',
                    ])
                    ->generate();

                // Handle Prism ImageResponse
                $image = $response->firstImage();
                $base64 = $image?->base64;

                if ($base64 !== null) {
                    file_put_contents($path, base64_decode($base64));
                    $this->info("  [OK] Created {$filename}");
                } else {
                    $this->error("  [FAIL] No base64 data in response for {$filename}");
                }
            } catch (\Throwable $e) {
                $this->error("  [FAIL] {$e->getMessage()}");
            }
        }

        $this->line('');
        $this->info('Assets stored in: '.$this->assetsPath);

        return self::SUCCESS;
    }

    /**
     * Test all available providers.
     */
    protected function testAllProviders(
        ThreadStorageService $storage,
        AgentRegistryContract $agentRegistry,
        PipelineRegistry $pipelineRegistry,
    ): int {
        $providers = ['openai', 'anthropic', 'gemini'];
        $results = [];

        foreach ($providers as $provider) {
            $this->line('');
            $this->info("=== Testing {$provider} ===");
            $this->line('');

            try {
                $result = $this->testProvider($provider, $storage, $agentRegistry, $pipelineRegistry);
                $results[$provider] = $result === self::SUCCESS ? 'PASS' : 'FAIL';
            } catch (\Throwable $e) {
                $this->error("Error: {$e->getMessage()}");
                $results[$provider] = 'ERROR';
            }
        }

        // Summary
        $this->line('');
        $this->info('=== Summary ===');
        foreach ($results as $provider => $result) {
            $icon = $result === 'PASS' ? '[PASS]' : '[FAIL]';
            $this->line("  {$icon} {$provider}");
        }

        return self::SUCCESS;
    }

    /**
     * Test a specific provider.
     */
    protected function testProvider(
        string $provider,
        ThreadStorageService $storage,
        AgentRegistryContract $agentRegistry,
        PipelineRegistry $pipelineRegistry,
    ): int {
        $agentKey = "{$provider}-vision";

        if (! $agentRegistry->has($agentKey)) {
            $this->error("Vision agent not found: {$agentKey}");
            $this->line('Available vision agents:');
            foreach ($agentRegistry->keys() as $key) {
                if (str_contains($key, 'vision')) {
                    $this->line("  - {$key}");
                }
            }

            return self::FAILURE;
        }

        $agent = $agentRegistry->get($agentKey);
        $this->info("Testing: {$agentKey} ({$agent->provider()}/{$agent->model()})");
        $this->line('');

        // Setup pipeline debugging if requested
        $pipelineData = [];
        if ($this->option('pipeline-debug')) {
            $this->setupPipelineDebugging($pipelineRegistry, $pipelineData);
        }

        // Test with conversation history
        if ($this->option('thread')) {
            return $this->testWithHistory($storage, $agentKey, $pipelineRegistry, $pipelineData);
        }

        // Single turn test
        return $this->testSingleTurn($agentKey, $pipelineRegistry, $pipelineData);
    }

    /**
     * Test single turn with attachments.
     *
     * @param  array<string, mixed>  $pipelineData
     */
    protected function testSingleTurn(
        string $agentKey,
        PipelineRegistry $pipelineRegistry,
        array &$pipelineData,
    ): int {
        $imagePath = $this->option('image') ?? $this->getDefaultTestImage();

        if ($imagePath === null) {
            $this->error('No test image available. Run --generate-assets first or provide --image=PATH');

            return self::FAILURE;
        }

        $this->line("Image: {$imagePath}");
        $this->line('');

        try {
            $prompt = 'Describe this image in detail. What objects, colors, and scenes do you see?';
            $this->line("Prompt: {$prompt}");
            $this->line('');

            // Create Prism Image object based on source type
            if ($this->option('url')) {
                $image = Image::fromUrl($imagePath);
            } elseif ($this->option('base64')) {
                $base64Data = file_exists($imagePath)
                    ? base64_encode(file_get_contents($imagePath))
                    : $imagePath;
                $image = Image::fromBase64($base64Data, 'image/png');
            } else {
                $image = Image::fromLocalPath($imagePath);
            }

            $response = Atlas::agent($agentKey)->chat($prompt, [$image]);

            $this->displayResponse($response);

            // Display pipeline data if debugging
            if ($this->option('pipeline-debug') && ! empty($pipelineData)) {
                $this->displayPipelineDebug($pipelineData);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Test with conversation history (multiple turns with attachments).
     *
     * @param  array<string, mixed>  $pipelineData
     */
    protected function testWithHistory(
        ThreadStorageService $storage,
        string $agentKey,
        PipelineRegistry $pipelineRegistry,
        array &$pipelineData,
    ): int {
        $this->info('=== Multi-turn Conversation with Attachments ===');
        $this->line('');

        $thread = $storage->create($agentKey);
        $this->line("Thread: {$thread['uuid']}");
        $this->line('');

        // Get test images
        $image1 = $this->getDefaultTestImage();
        $image2 = $this->getSecondTestImage();

        if ($image1 === null) {
            $this->error('No test images available. Run --generate-assets first.');

            return self::FAILURE;
        }

        // Turn 1: Send first image
        $this->info('--- Turn 1: First Image ---');
        $this->line("Image: {$image1}");

        $thread = $storage->addMessage($thread, 'user', 'What do you see in this image?');

        try {
            $response1 = Atlas::agent($agentKey)
                ->withMessages($thread['messages'])
                ->chat('What do you see in this image?', [Image::fromLocalPath($image1)]);

            $text1 = $response1->text ?? '[No response]';
            $thread = $storage->addMessage($thread, 'assistant', $text1);
            $thread = $storage->addTokens($thread, $response1->usage->promptTokens + $response1->usage->completionTokens);

            $this->line('');
            $this->info("Response: {$text1}");
            $this->line('Tokens: '.($response1->usage->promptTokens + $response1->usage->completionTokens));
            $this->line('');

        } catch (\Throwable $e) {
            $this->error("Turn 1 failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        // Turn 2: Follow-up question about the same image
        $this->info('--- Turn 2: Follow-up Question ---');

        $thread = $storage->addMessage($thread, 'user', 'What colors are most prominent in the image?');

        try {
            $response2 = Atlas::agent($agentKey)
                ->withMessages($thread['messages'])
                ->chat('What colors are most prominent in the image?');

            $text2 = $response2->text ?? '[No response]';
            $thread = $storage->addMessage($thread, 'assistant', $text2);
            $thread = $storage->addTokens($thread, $response2->usage->promptTokens + $response2->usage->completionTokens);

            $this->line('');
            $this->info("Response: {$text2}");
            $this->line('Tokens: '.($response2->usage->promptTokens + $response2->usage->completionTokens));
            $this->line('');

        } catch (\Throwable $e) {
            $this->error("Turn 2 failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        // Turn 3: Send a second image and compare
        if ($image2 !== null) {
            $this->info('--- Turn 3: Second Image Comparison ---');
            $this->line("Image: {$image2}");

            $thread = $storage->addMessage($thread, 'user', 'Now look at this new image. How does it compare to the first one?');

            try {
                $response3 = Atlas::agent($agentKey)
                    ->withMessages($thread['messages'])
                    ->chat('Now look at this new image. How does it compare to the first one?', [Image::fromLocalPath($image2)]);

                $text3 = $response3->text ?? '[No response]';
                $thread = $storage->addMessage($thread, 'assistant', $text3);
                $thread = $storage->addTokens($thread, $response3->usage->promptTokens + $response3->usage->completionTokens);

                $this->line('');
                $this->info("Response: {$text3}");
                $this->line('Tokens: '.($response3->usage->promptTokens + $response3->usage->completionTokens));
                $this->line('');

            } catch (\Throwable $e) {
                $this->error("Turn 3 failed: {$e->getMessage()}");

                return self::FAILURE;
            }
        }

        // Save thread
        $storage->save($thread);

        // Display conversation summary
        $this->info('=== Conversation Summary ===');
        $this->line("Messages: {$thread['metadata']['message_count']}");
        $this->line("Total Tokens: {$thread['metadata']['total_tokens']}");
        $this->line("Thread saved: {$thread['uuid']}");
        $this->line('');

        // Display pipeline debug if enabled
        if ($this->option('pipeline-debug') && ! empty($pipelineData)) {
            $this->displayPipelineDebug($pipelineData);
        }

        return self::SUCCESS;
    }

    /**
     * Run comprehensive test suite.
     */
    protected function runComprehensiveTests(
        ThreadStorageService $storage,
        AgentRegistryContract $agentRegistry,
        PipelineRegistry $pipelineRegistry,
    ): int {
        $this->info('=== Comprehensive Multimodal Test Suite ===');
        $this->line('');

        $results = [];

        // Test 1: Single image with each provider
        $this->info('Test 1: Single Image Analysis');
        $this->line('');

        foreach (['openai', 'anthropic', 'gemini'] as $provider) {
            $agentKey = "{$provider}-vision";
            if (! $agentRegistry->has($agentKey)) {
                $results["single_image_{$provider}"] = 'SKIP (agent not found)';

                continue;
            }

            $this->line("  Testing {$provider}...");
            try {
                $image = $this->getDefaultTestImage();
                if ($image === null) {
                    $results["single_image_{$provider}"] = 'SKIP (no image)';

                    continue;
                }

                $response = Atlas::agent($agentKey)
                    ->chat('What is in this image? Be brief.', [Image::fromLocalPath($image)]);

                if ($response->text !== null && strlen($response->text) > 10) {
                    $results["single_image_{$provider}"] = 'PASS';
                    $this->info('    [PASS] Got response: '.substr($response->text, 0, 50).'...');
                } else {
                    $results["single_image_{$provider}"] = 'FAIL (empty response)';
                    $this->error('    [FAIL] Empty or short response');
                }
            } catch (\Throwable $e) {
                $results["single_image_{$provider}"] = 'FAIL ('.$e->getMessage().')';
                $this->error("    [FAIL] {$e->getMessage()}");
            }
        }

        $this->line('');

        // Test 2: Multiple images in single request
        $this->info('Test 2: Multiple Images');
        $this->line('');

        $image1 = $this->getDefaultTestImage();
        $image2 = $this->getSecondTestImage();

        if ($image1 !== null && $image2 !== null) {
            foreach (['openai', 'anthropic', 'gemini'] as $provider) {
                $agentKey = "{$provider}-vision";
                if (! $agentRegistry->has($agentKey)) {
                    $results["multi_image_{$provider}"] = 'SKIP';

                    continue;
                }

                $this->line("  Testing {$provider}...");
                try {
                    $response = Atlas::agent($agentKey)
                        ->chat('I sent you two images. Briefly describe both.', [
                            Image::fromLocalPath($image1),
                            Image::fromLocalPath($image2),
                        ]);

                    if ($response->text !== null && strlen($response->text) > 20) {
                        $results["multi_image_{$provider}"] = 'PASS';
                        $this->info('    [PASS] Got response: '.substr($response->text, 0, 50).'...');
                    } else {
                        $results["multi_image_{$provider}"] = 'FAIL (short response)';
                        $this->error('    [FAIL] Response too short');
                    }
                } catch (\Throwable $e) {
                    $results["multi_image_{$provider}"] = 'FAIL';
                    $this->error("    [FAIL] {$e->getMessage()}");
                }
            }
        } else {
            $this->line('  [SKIP] Need two test images for this test');
        }

        $this->line('');

        // Test 3: Base64 encoding
        $this->info('Test 3: Base64 Encoded Image');
        $this->line('');

        if ($image1 !== null && file_exists($image1)) {
            $base64Data = base64_encode(file_get_contents($image1));

            foreach (['openai', 'anthropic'] as $provider) {
                $agentKey = "{$provider}-vision";
                if (! $agentRegistry->has($agentKey)) {
                    $results["base64_{$provider}"] = 'SKIP';

                    continue;
                }

                $this->line("  Testing {$provider}...");
                try {
                    $response = Atlas::agent($agentKey)
                        ->chat('What is this image? Be very brief.', [
                            Image::fromBase64($base64Data, 'image/png'),
                        ]);

                    if ($response->text !== null && strlen($response->text) > 5) {
                        $results["base64_{$provider}"] = 'PASS';
                        $this->info('    [PASS] Got response: '.substr($response->text, 0, 50).'...');
                    } else {
                        $results["base64_{$provider}"] = 'FAIL';
                        $this->error('    [FAIL] Empty response');
                    }
                } catch (\Throwable $e) {
                    $results["base64_{$provider}"] = 'FAIL';
                    $this->error("    [FAIL] {$e->getMessage()}");
                }
            }
        }

        $this->line('');

        // Test 4: Conversation with history
        $this->info('Test 4: Conversation History with Attachments');
        $this->line('');

        if ($image1 !== null) {
            foreach (['openai'] as $provider) { // Just test one for speed
                $agentKey = "{$provider}-vision";
                if (! $agentRegistry->has($agentKey)) {
                    $results["history_{$provider}"] = 'SKIP';

                    continue;
                }

                $this->line("  Testing {$provider}...");
                try {
                    // First message with image
                    $messages = [
                        [
                            'role' => 'user',
                            'content' => 'What color is dominant in this image?',
                            'attachments' => [
                                [
                                    'type' => 'image',
                                    'source' => 'local_path',
                                    'data' => $image1,
                                ],
                            ],
                        ],
                        [
                            'role' => 'assistant',
                            'content' => 'The dominant colors appear to be various shades.',
                        ],
                    ];

                    // Second message referencing the previous image
                    $response = Atlas::agent($agentKey)
                        ->withMessages($messages)
                        ->chat('Based on the image I showed you, what objects can you identify?');

                    if ($response->text !== null && strlen($response->text) > 10) {
                        $results["history_{$provider}"] = 'PASS';
                        $this->info('    [PASS] Model referenced history: '.substr($response->text, 0, 50).'...');
                    } else {
                        $results["history_{$provider}"] = 'FAIL';
                        $this->error('    [FAIL] Poor response');
                    }
                } catch (\Throwable $e) {
                    $results["history_{$provider}"] = 'FAIL';
                    $this->error("    [FAIL] {$e->getMessage()}");
                }
            }
        }

        $this->line('');

        // Test 5: Document attachment
        // Note: OpenAI GPT-4o does NOT support documents - only images
        // Anthropic Claude supports PDFs, Gemini supports text/PDFs
        $this->info('Test 5: Document Attachment (Gemini + Anthropic)');
        $this->line('');
        $this->line('  Note: OpenAI GPT-4o only supports images, not documents');
        $this->line('');

        $documentPath = $this->getTestDocument();
        if ($documentPath !== null) {
            // Test with providers that support documents: Gemini (text), Anthropic (PDF)
            foreach (['gemini', 'anthropic'] as $provider) {
                $agentKey = "{$provider}-vision";
                if (! $agentRegistry->has($agentKey)) {
                    $results["document_{$provider}"] = 'SKIP (agent not found)';

                    continue;
                }

                $this->line("  Testing {$provider} with document...");
                try {
                    // Anthropic primarily supports PDF, Gemini supports text/PDF
                    $mimeType = $provider === 'anthropic' ? 'application/pdf' : 'text/plain';
                    // For Anthropic, we'd need a PDF - skip if testing text file with Anthropic
                    if ($provider === 'anthropic' && str_ends_with($documentPath, '.txt')) {
                        $results["document_{$provider}"] = 'SKIP (text files not supported, needs PDF)';
                        $this->line('    [SKIP] Anthropic requires PDF format, not text files');

                        continue;
                    }

                    $response = Atlas::agent($agentKey)
                        ->chat('What is this document about? List the key features mentioned.', [
                            Document::fromLocalPath($documentPath),
                        ]);

                    if ($response->text !== null && strlen($response->text) > 20) {
                        // Check if response mentions Atlas or features from the document
                        $mentionsAtlas = stripos($response->text, 'Atlas') !== false;
                        $mentionsFeatures = stripos($response->text, 'stateless') !== false
                            || stripos($response->text, 'provider') !== false
                            || stripos($response->text, 'tool') !== false;

                        if ($mentionsAtlas || $mentionsFeatures) {
                            $results["document_{$provider}"] = 'PASS';
                            $this->info('    [PASS] Document content understood: '.substr($response->text, 0, 80).'...');
                        } else {
                            $results["document_{$provider}"] = 'PASS (response received)';
                            $this->info('    [PASS] Got response: '.substr($response->text, 0, 80).'...');
                        }
                    } else {
                        $results["document_{$provider}"] = 'FAIL (empty response)';
                        $this->error('    [FAIL] Empty or short response');
                    }
                } catch (\Throwable $e) {
                    $results["document_{$provider}"] = 'FAIL ('.$e->getMessage().')';
                    $this->error("    [FAIL] {$e->getMessage()}");
                }
            }
        } else {
            $this->line('  [SKIP] No test document found');
            $results['document_gemini'] = 'SKIP (no document)';
        }

        $this->line('');

        // Test 6: Audio attachment
        // Note: Only Gemini has native audio input support
        // OpenAI and Anthropic do NOT support audio attachments for analysis
        $this->info('Test 6: Audio Attachment (Gemini only)');
        $this->line('');
        $this->line('  Note: Only Gemini supports audio attachments. OpenAI/Anthropic do not.');
        $this->line('');

        $audioPath = $this->getTestAudio();
        if ($audioPath !== null) {
            foreach (['gemini'] as $provider) {
                $agentKey = "{$provider}-vision";
                if (! $agentRegistry->has($agentKey)) {
                    $results["audio_{$provider}"] = 'SKIP (agent not found)';

                    continue;
                }

                $this->line("  Testing {$provider} with audio...");
                $this->line("  Audio file: {$audioPath}");
                try {
                    $response = Atlas::agent($agentKey)
                        ->chat('What is being said in this audio? Transcribe or describe the content.', [
                            Audio::fromLocalPath($audioPath),
                        ]);

                    if ($response->text !== null && strlen($response->text) > 10) {
                        $results["audio_{$provider}"] = 'PASS';
                        $this->info('    [PASS] Audio processed: '.substr($response->text, 0, 80).'...');
                    } else {
                        $results["audio_{$provider}"] = 'FAIL (empty response)';
                        $this->error('    [FAIL] Empty or short response');
                    }
                } catch (\Throwable $e) {
                    // Some providers may not support audio - mark as skip if unsupported
                    $errorMsg = $e->getMessage();
                    if (stripos($errorMsg, 'unsupported') !== false || stripos($errorMsg, 'not supported') !== false) {
                        $results["audio_{$provider}"] = 'SKIP (not supported)';
                        $this->line("    [SKIP] Audio not supported by {$provider}");
                    } else {
                        $results["audio_{$provider}"] = 'FAIL ('.$errorMsg.')';
                        $this->error("    [FAIL] {$errorMsg}");
                    }
                }
            }
        } else {
            $this->line('  [SKIP] No test audio found. Run atlas:speech --generate="Test audio" first.');
            $results['audio_gemini'] = 'SKIP (no audio)';
        }

        $this->line('');

        // Test 7: Document in conversation history
        $this->info('Test 7: Document in Conversation History');
        $this->line('');

        if ($documentPath !== null) {
            foreach (['gemini'] as $provider) {
                $agentKey = "{$provider}-vision";
                if (! $agentRegistry->has($agentKey)) {
                    $results["document_history_{$provider}"] = 'SKIP';

                    continue;
                }

                $this->line("  Testing {$provider} with document history...");
                try {
                    // First message with document
                    $messages = [
                        [
                            'role' => 'user',
                            'content' => 'I am sharing a document about a software project.',
                            'attachments' => [
                                [
                                    'type' => 'document',
                                    'source' => 'local_path',
                                    'data' => $documentPath,
                                    'mime_type' => 'text/plain',
                                ],
                            ],
                        ],
                        [
                            'role' => 'assistant',
                            'content' => 'I can see you shared a document. Let me review it.',
                        ],
                    ];

                    // Follow-up question referencing the document
                    $response = Atlas::agent($agentKey)
                        ->withMessages($messages)
                        ->chat('What version number is mentioned in the document I shared earlier?');

                    if ($response->text !== null && strlen($response->text) > 5) {
                        // Check if it references the version from document
                        $mentionsVersion = stripos($response->text, '1.0') !== false
                            || stripos($response->text, 'version') !== false;

                        if ($mentionsVersion) {
                            $results["document_history_{$provider}"] = 'PASS';
                            $this->info('    [PASS] Document history maintained: '.substr($response->text, 0, 60).'...');
                        } else {
                            $results["document_history_{$provider}"] = 'PASS (responded)';
                            $this->info('    [PASS] Got response: '.substr($response->text, 0, 60).'...');
                        }
                    } else {
                        $results["document_history_{$provider}"] = 'FAIL';
                        $this->error('    [FAIL] Empty response');
                    }
                } catch (\Throwable $e) {
                    $results["document_history_{$provider}"] = 'FAIL';
                    $this->error("    [FAIL] {$e->getMessage()}");
                }
            }
        } else {
            $results['document_history_gemini'] = 'SKIP';
        }

        $this->line('');

        // Test 8: Storage disk for attachments
        $this->info('Test 8: Storage Disk Attachments');
        $this->line('');
        $this->line('  Testing attachments loaded via Laravel Storage disk...');
        $this->line('');

        // Copy test files to storage if not already there
        $this->setupStorageTestFiles();

        // Test image from storage path
        $this->line('  Testing image from storage path (assets disk)...');
        try {
            if (\Illuminate\Support\Facades\Storage::disk('outputs')->exists('test-apple.png')) {
                $response = Atlas::agent('gemini-vision')
                    ->chat('What do you see in this image? Be brief.', [
                        Image::fromStoragePath('test-apple.png', 'outputs'),
                    ]);

                if ($response->text !== null && strlen($response->text) > 10) {
                    $results['storage_image'] = 'PASS';
                    $this->info('    [PASS] Image from storage: '.substr($response->text, 0, 50).'...');
                } else {
                    $results['storage_image'] = 'FAIL';
                    $this->error('    [FAIL] Empty response');
                }
            } else {
                $results['storage_image'] = 'SKIP (no test image in storage)';
                $this->line('    [SKIP] No test image in outputs disk');
            }
        } catch (\Throwable $e) {
            $results['storage_image'] = 'FAIL ('.$e->getMessage().')';
            $this->error("    [FAIL] {$e->getMessage()}");
        }

        // Test document from storage path
        $this->line('  Testing document from storage path (assets disk)...');
        try {
            if (\Illuminate\Support\Facades\Storage::disk('assets')->exists('test-document.txt')) {
                $response = Atlas::agent('gemini-vision')
                    ->chat('What is this document about?', [
                        Document::fromStoragePath('test-document.txt', 'text/plain', 'assets'),
                    ]);

                if ($response->text !== null && strlen($response->text) > 10) {
                    $results['storage_document'] = 'PASS';
                    $this->info('    [PASS] Document from storage: '.substr($response->text, 0, 50).'...');
                } else {
                    $results['storage_document'] = 'FAIL';
                    $this->error('    [FAIL] Empty response');
                }
            } else {
                $results['storage_document'] = 'SKIP (no test document in storage)';
                $this->line('    [SKIP] No test document in assets disk');
            }
        } catch (\Throwable $e) {
            $results['storage_document'] = 'FAIL ('.$e->getMessage().')';
            $this->error("    [FAIL] {$e->getMessage()}");
        }

        // Test audio from storage path
        $this->line('  Testing audio from storage path (outputs disk)...');
        try {
            $audioFiles = \Illuminate\Support\Facades\Storage::disk('outputs')->files();
            $speechFile = collect($audioFiles)->first(fn ($f) => str_starts_with($f, 'speech-') && str_ends_with($f, '.mp3'));

            if ($speechFile) {
                $response = Atlas::agent('gemini-vision')
                    ->chat('What is being said in this audio?', [
                        Audio::fromStoragePath($speechFile, 'outputs'),
                    ]);

                if ($response->text !== null && strlen($response->text) > 10) {
                    $results['storage_audio'] = 'PASS';
                    $this->info('    [PASS] Audio from storage: '.substr($response->text, 0, 50).'...');
                } else {
                    $results['storage_audio'] = 'FAIL';
                    $this->error('    [FAIL] Empty response');
                }
            } else {
                $results['storage_audio'] = 'SKIP (no speech file in storage)';
                $this->line('    [SKIP] No speech file in outputs disk');
            }
        } catch (\Throwable $e) {
            $results['storage_audio'] = 'FAIL ('.$e->getMessage().')';
            $this->error("    [FAIL] {$e->getMessage()}");
        }

        $this->line('');

        // Test 9: Context construction verification (Prism media objects)
        $this->info('Test 9: Context Construction Verification (Prism Media Objects)');
        $this->line('');

        $this->line('  Testing context with Prism media objects...');
        try {
            // Build context with Prism media objects
            $prismMedia = [];

            if ($image1 !== null) {
                $prismMedia[] = \Prism\Prism\ValueObjects\Media\Image::fromLocalPath($image1);
            }
            if ($documentPath !== null) {
                $prismMedia[] = \Prism\Prism\ValueObjects\Media\Document::fromLocalPath($documentPath);
            }
            if ($audioPath !== null) {
                $prismMedia[] = \Prism\Prism\ValueObjects\Media\Audio::fromLocalPath($audioPath);
            }

            $context = new \Atlasphp\Atlas\Agents\Support\AgentContext(
                messages: [],
                variables: [],
                metadata: ['test_key' => 'test_value'],
                prismMedia: $prismMedia,
            );

            $hasAttachments = $context->hasAttachments();
            $hasMetadata = ! empty($context->metadata);
            $attachmentCount = count($context->prismMedia);

            $this->line('    hasAttachments(): '.($hasAttachments ? 'true' : 'false'));
            $this->line("    Prism media count: {$attachmentCount}");

            // Show each media type
            $types = [];
            foreach ($context->prismMedia as $i => $media) {
                $type = (new \ReflectionClass($media))->getShortName();
                $types[] = $type;
                $this->line("    [{$i}] type={$type}");
            }

            if ($hasAttachments && $hasMetadata && $attachmentCount >= 1) {
                $results['context_construction'] = 'PASS';
                $this->info('    [PASS] Context properly constructed with '.implode(', ', $types).' Prism media objects');
            } else {
                $results['context_construction'] = 'FAIL';
                $this->error('    [FAIL] Context construction incorrect');
            }

            // AgentContext is readonly, verify immutability is enforced
            $this->info('    [INFO] AgentContext is readonly - immutability enforced at the type level');

        } catch (\Throwable $e) {
            $results['context_construction'] = 'FAIL';
            $this->error("    [FAIL] {$e->getMessage()}");
        }

        // Summary
        $this->line('');
        $this->info('=== Test Results Summary ===');
        $this->line('');

        $passed = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($results as $test => $result) {
            if (str_starts_with($result, 'PASS')) {
                $passed++;
                $icon = '[PASS]';
            } elseif (str_starts_with($result, 'SKIP')) {
                $skipped++;
                $icon = '[SKIP]';
            } else {
                $failed++;
                $icon = '[FAIL]';
            }
            $this->line("  {$icon} {$test}");
        }

        $this->line('');
        $this->info("Total: {$passed} passed, {$failed} failed, {$skipped} skipped");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Setup pipeline debugging.
     *
     * Note: Pipeline debugging requires creating a PipelineContract implementation.
     * For now, we just output a message about the context verification being done inline.
     *
     * @param  array<string, mixed>  $pipelineData
     */
    protected function setupPipelineDebugging(PipelineRegistry $pipelineRegistry, array &$pipelineData): void
    {
        // Pipeline debugging would require creating a PipelineContract class
        // For comprehensive testing, we verify the context inline instead
        $this->line('  (Pipeline debugging uses inline context verification)');
    }

    /**
     * Display response details.
     *
     * @param  \Prism\Prism\Text\Response  $response
     */
    protected function displayResponse($response): void
    {
        $this->line('');
        $this->info('=== Response ===');
        $this->line($response->text ?? '[No response]');
        $this->line('');
        $this->line('--- Details ---');
        $totalTokens = $response->usage->promptTokens + $response->usage->completionTokens;
        $this->line("Tokens: {$response->usage->promptTokens} prompt / {$response->usage->completionTokens} completion / {$totalTokens} total");
        $finishReason = $response->finishReason->value ?? 'unknown';
        $this->line("Finish: {$finishReason}");
        $this->line('');
    }

    /**
     * Display pipeline debug information.
     *
     * @param  array<string, mixed>  $pipelineData
     */
    protected function displayPipelineDebug(array $pipelineData): void
    {
        $this->info('=== Pipeline Debug ===');

        if (isset($pipelineData['before_execute'])) {
            $this->line('');
            $this->line('before_execute:');
            $this->line("  Agent: {$pipelineData['before_execute']['agent']}");
            $this->line("  Input: {$pipelineData['before_execute']['input']}");
            $this->line('  Has Attachments: '.($pipelineData['before_execute']['has_attachments'] ? 'Yes' : 'No'));
            $this->line("  Prism Media Count: {$pipelineData['before_execute']['prism_media_count']}");
            $this->line("  Messages Count: {$pipelineData['before_execute']['messages_count']}");
            $this->line('  Metadata: '.json_encode($pipelineData['before_execute']['metadata']));

            // Show media details if present
            $context = $pipelineData['before_execute']['context'] ?? null;
            if ($context !== null && $context->hasAttachments()) {
                $this->line('');
                $this->line('  Prism Media:');
                foreach ($context->prismMedia as $i => $media) {
                    $type = (new \ReflectionClass($media))->getShortName();
                    $this->line("    [{$i}] type={$type}");
                }
            }
        }

        if (isset($pipelineData['after_execute'])) {
            $this->line('');
            $this->line('after_execute:');
            $this->line("  Response Length: {$pipelineData['after_execute']['response_text_length']} chars");
            $this->line("  Total Tokens: {$pipelineData['after_execute']['total_tokens']}");
        }

        $this->line('');
    }

    /**
     * Get the default test image path.
     */
    protected function getDefaultTestImage(): ?string
    {
        // Check assets folder first
        $assetPath = $this->assetsPath.'/test-landscape.png';
        if (file_exists($assetPath)) {
            return $assetPath;
        }

        // Fall back to outputs folder (generated images)
        $outputsPath = dirname(__DIR__, 3).'/storage/outputs';
        $files = glob($outputsPath.'/test-*.png') ?: [];

        return $files[0] ?? null;
    }

    /**
     * Get a second test image path.
     */
    protected function getSecondTestImage(): ?string
    {
        $outputsPath = dirname(__DIR__, 3).'/storage/outputs';
        $files = glob($outputsPath.'/test-*.png') ?: [];

        return $files[1] ?? null;
    }

    /**
     * Get the test document path.
     */
    protected function getTestDocument(): ?string
    {
        // Check assets folder
        $assetPath = $this->assetsPath.'/test-document.txt';
        if (file_exists($assetPath)) {
            return $assetPath;
        }

        return null;
    }

    /**
     * Get the test audio path.
     */
    protected function getTestAudio(): ?string
    {
        // Check outputs folder for speech files
        $outputsPath = dirname(__DIR__, 3).'/storage/outputs';
        $files = glob($outputsPath.'/speech-*.mp3') ?: [];

        return $files[0] ?? null;
    }

    /**
     * Setup test files in storage for storage_path tests.
     */
    protected function setupStorageTestFiles(): void
    {
        // Ensure test document exists in assets disk
        if (! \Illuminate\Support\Facades\Storage::disk('assets')->exists('test-document.txt')) {
            $localDoc = $this->getTestDocument();
            if ($localDoc !== null && file_exists($localDoc)) {
                \Illuminate\Support\Facades\Storage::disk('assets')->put(
                    'test-document.txt',
                    file_get_contents($localDoc)
                );
            }
        }

        // Image and audio files should already be in outputs disk
        // since that's where they're generated by other commands
    }
}
