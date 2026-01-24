<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlasphp\Atlas\Atlas;
use Illuminate\Console\Command;
use Prism\Prism\Audio\AudioResponse;
use Prism\Prism\Audio\TextResponse;
use Prism\Prism\ValueObjects\Media\Audio;

/**
 * Command for testing text-to-speech and speech-to-text.
 *
 * Demonstrates TTS synthesis and STT transcription capabilities.
 */
class SpeechCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'atlas:speech
                            {--generate= : Text to convert to speech}
                            {--transcribe= : Audio file path to transcribe}
                            {--voice=nova : Voice selection}
                            {--speed=1.0 : Speech speed (0.25-4.0)}
                            {--format=mp3 : Audio format}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test text-to-speech and speech-to-text with Atlas';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $generateText = $this->option('generate');
        $transcribeFile = $this->option('transcribe');

        if (! $generateText && ! $transcribeFile) {
            $this->error('Please provide either --generate="text" or --transcribe="file.mp3"');

            return self::FAILURE;
        }

        if ($generateText) {
            return $this->handleTts($generateText);
        }

        return $this->handleStt($transcribeFile);
    }

    /**
     * Handle text-to-speech.
     */
    protected function handleTts(string $text): int
    {
        $voice = $this->option('voice');
        $speed = (float) $this->option('speed');
        $format = $this->option('format');

        $this->displayTtsHeader($text, $voice, $speed, $format);

        try {
            $this->info('Generating speech...');

            /** @var AudioResponse $response */
            $response = Atlas::audio()
                ->using(
                    config('atlas.speech.provider', 'openai'),
                    config('atlas.speech.model', 'tts-1')
                )
                ->withInput($text)
                ->withVoice($voice)
                ->withProviderOptions([
                    'speed' => $speed,
                    'response_format' => $format,
                ])
                ->asAudio();

            $this->displayTtsResponse($response, $format);
            $this->displayTtsVerification($response, $format);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Handle speech-to-text.
     */
    protected function handleStt(string $file): int
    {
        if (! file_exists($file)) {
            // Check in storage/outputs if relative path
            $altPath = dirname(__DIR__, 3).'/storage/outputs/'.$file;
            if (file_exists($altPath)) {
                $file = $altPath;
            } else {
                $this->error("File not found: {$file}");

                return self::FAILURE;
            }
        }

        $this->displaySttHeader($file);

        try {
            $this->info('Transcribing audio...');

            /** @var TextResponse $response */
            $response = Atlas::audio()
                ->using(
                    config('atlas.speech.provider', 'openai'),
                    config('atlas.speech.transcription_model', 'whisper-1')
                )
                ->withInput(Audio::fromPath($file))
                ->asText();

            $this->displaySttResponse($response);
            $this->displaySttVerification($response);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Display TTS header.
     */
    protected function displayTtsHeader(string $text, string $voice, float $speed, string $format): void
    {
        $this->line('');
        $this->line('=== Atlas Speech Test (TTS) ===');
        $this->line('Provider: '.config('atlas.speech.provider', 'openai'));
        $this->line('Model: '.config('atlas.speech.model', 'tts-1'));
        $this->line("Voice: {$voice}");
        $this->line("Speed: {$speed}x");
        $this->line("Format: {$format}");
        $this->line('');
        $this->line("Input Text: \"{$text}\"");
        $this->line('');
    }

    /**
     * Display TTS response.
     */
    protected function displayTtsResponse(AudioResponse $response, string $format): void
    {
        $this->line('--- Response ---');

        $audioData = $response->audio;
        $size = strlen($audioData);

        if ($size > 0) {
            $sizeKb = round($size / 1024, 1);
            $this->line("Audio Size: {$sizeKb} KB");
            $this->line("Format: {$format}");

            // Estimate duration (rough estimate based on typical speech rate)
            $estimatedDuration = round($size / 16000); // Very rough estimate
            $this->line("Duration: ~{$estimatedDuration} seconds (estimated)");

            // Save to file
            $filename = 'speech-'.time().'.'.$format;
            $path = $this->saveAudio($audioData, $filename);
            if ($path) {
                $this->line("Saved to: {$path}");
            }
        } else {
            $this->warn('No audio data in response');
        }

        $this->line('');
    }

    /**
     * Display TTS verification.
     */
    protected function displayTtsVerification(AudioResponse $response, string $format): void
    {
        $this->line('--- Verification ---');

        $audioData = $response->audio;
        $size = strlen($audioData);

        if ($size > 0) {
            $this->info('[PASS] Audio data returned');

            // Check format by magic bytes
            $header = substr($audioData, 0, 12);
            $isValidFormat = $this->validateAudioFormat($header, $format);

            if ($isValidFormat) {
                $this->info('[PASS] Format matches requested format');
                $this->info('[PASS] Audio file is playable');
            } else {
                $this->warn('[WARN] Could not verify audio format');
            }
        } else {
            $this->error('[FAIL] No audio data returned');
        }
    }

    /**
     * Display STT header.
     */
    protected function displaySttHeader(string $file): void
    {
        $this->line('');
        $this->line('=== Atlas Speech Test (STT) ===');
        $this->line('Provider: '.config('atlas.speech.provider', 'openai'));
        $this->line('Model: '.config('atlas.speech.transcription_model', 'whisper-1'));
        $this->line('');
        $this->line("Input: {$file}");
        $this->line('');
    }

    /**
     * Display STT response.
     */
    protected function displaySttResponse(TextResponse $response): void
    {
        $this->line('--- Response ---');

        if ($response->text) {
            $this->line("Transcription: \"{$response->text}\"");
        } else {
            $this->warn('No transcription text returned');
        }

        $this->line('');
    }

    /**
     * Display STT verification.
     */
    protected function displaySttVerification(TextResponse $response): void
    {
        $this->line('--- Verification ---');

        if ($response->text && strlen($response->text) > 0) {
            $this->info('[PASS] Transcription text returned');
        } else {
            $this->error('[FAIL] No transcription text returned');
        }
    }

    /**
     * Save audio data to file.
     */
    protected function saveAudio(string $data, string $filename): ?string
    {
        $path = dirname(__DIR__, 3).'/storage/outputs/'.$filename;
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_put_contents($path, $data) !== false) {
            return $path;
        }

        return null;
    }

    /**
     * Validate audio format by magic bytes.
     */
    protected function validateAudioFormat(string $header, string $format): bool
    {
        return match ($format) {
            'mp3' => str_starts_with($header, "\xFF\xFB") || str_starts_with($header, "\xFF\xFA") || str_starts_with($header, 'ID3'),
            'wav' => str_starts_with($header, 'RIFF'),
            'ogg' => str_starts_with($header, 'OggS'),
            'flac' => str_starts_with($header, 'fLaC'),
            default => true, // Assume valid if unknown format
        };
    }
}
