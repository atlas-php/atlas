<?php

declare(strict_types=1);

/**
 * ElevenLabs Provider Integration Test
 *
 * Validates ElevenLabs TTS, SFX, music generation, STT,
 * and provider metadata endpoints against the real API.
 *
 * Usage: php test-elevenlabs-provider.php
 *
 * Requires ELEVENLABS_API_KEY in sandbox/.env
 *
 * Generated audio files are saved to sandbox/storage/providers/elevenlabs/
 * for manual review. The directory is wiped at the start of each run.
 *
 * Note: Some features require a paid ElevenLabs plan.
 * Tests that require paid features are gracefully skipped
 * when the API returns 402 (payment required).
 */
$app = require __DIR__.'/bootstrap.php';

$app['config']->set('atlas.providers.elevenlabs', [
    'api_key' => env('ELEVENLABS_API_KEY'),
    'url' => env('ELEVENLABS_URL', 'https://api.elevenlabs.io/v1'),
    'media_timeout' => 300,
]);

// Use array cache for sandbox tests to avoid SQLite DB issues
$app['config']->set('atlas.cache.store', 'array');
$app['config']->set('atlas.cache.ttl.models', 0);
$app['config']->set('atlas.cache.ttl.voices', 0);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Input\Audio;
use Illuminate\Http\Client\RequestException;

// ─── Storage Setup ───────────────────────────────────────────────────────────

$storageDir = __DIR__.'/storage/providers/elevenlabs';

// Wipe previous test output
if (is_dir($storageDir)) {
    $files = glob("{$storageDir}/*");
    if ($files !== false) {
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
} else {
    mkdir($storageDir, 0755, true);
}

function saveAudio(string $name, string $base64Data, string $format = 'mp3'): void
{
    global $storageDir;

    $path = "{$storageDir}/{$name}.{$format}";
    file_put_contents($path, base64_decode($base64Data));
    echo " → saved {$name}.{$format}";
}

// ─── Test Runner ─────────────────────────────────────────────────────────────

$passed = 0;
$failed = 0;
$skipped = 0;
$errors = [];

function test(string $name, Closure $fn): void
{
    global $passed, $failed, $skipped, $errors;

    echo "\n  {$name} ";

    try {
        $fn();
        echo ' ✓';
        $passed++;
    } catch (RequestException $e) {
        if ($e->response->status() === 402) {
            echo '⊘ SKIP (paid plan required)';
            $skipped++;
        } elseif ($e->response->status() === 401) {
            echo '⊘ SKIP (missing API permission)';
            $skipped++;
        } else {
            echo '✗ FAIL';
            $msg = get_class($e).': '.$e->getMessage();
            $errors[] = "  {$name}: {$msg}";
            $failed++;
        }
    } catch (Throwable $e) {
        echo '✗ FAIL';
        $msg = get_class($e).': '.$e->getMessage();
        $errors[] = "  {$name}: {$msg}";
        $failed++;
    }
}

function skip(string $name, string $reason): void
{
    global $skipped;

    echo "\n  {$name} ⊘ SKIP ({$reason})";
    $skipped++;
}

function assert_true(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
}

// ─── Tests ───────────────────────────────────────────────────────────────────

echo '╔══════════════════════════════════════════════╗';
echo "\n║   ElevenLabs Provider Integration Tests      ║";
echo "\n╚══════════════════════════════════════════════╝";

// ── Provider Metadata ────────────────────────────────────────────────────────

echo "\n\n── Provider Metadata";

test('list models', function () {
    $models = Atlas::provider(Provider::ElevenLabs)->models();

    assert_true(count($models->models) > 0, 'Should return at least one model');
    assert_true(in_array('eleven_multilingual_v2', $models->models, true), 'Should include eleven_multilingual_v2');

    echo '('.count($models->models).' models)';
});

test('list voices', function () {
    $voices = Atlas::provider(Provider::ElevenLabs)->voices();

    assert_true(count($voices->voices) > 0, 'Should return at least one voice');

    echo '('.count($voices->voices).' voices)';
});

test('validate returns true', function () {
    $valid = Atlas::provider(Provider::ElevenLabs)->validate();

    assert_true($valid === true, 'validate() should return true');
});

// ── Text-to-Speech ───────────────────────────────────────────────────────────

echo "\n\n── Text-to-Speech";

test('basic TTS generation', function () {
    $r = Atlas::audio(Provider::ElevenLabs, 'eleven_multilingual_v2')
        ->instructions('Hello, this is a test of the ElevenLabs text to speech system.')
        ->withVoice('21m00Tcm4TlvDq8ikWAM') // Rachel
        ->asAudio();

    $decoded = base64_decode($r->data);
    assert_true(strlen($decoded) > 1000, 'Audio data should be substantial ('.strlen($decoded).' bytes)');
    assert_true($r->format === 'mp3', "Format should be mp3, got: {$r->format}");

    saveAudio('tts-basic', $r->data, $r->format);
    echo ' ('.round(strlen($decoded) / 1024).' KB)';
});

test('TTS with speed and language', function () {
    $r = Atlas::audio(Provider::ElevenLabs, 'eleven_multilingual_v2')
        ->instructions('This is a speed test at one point two times speed.')
        ->withVoice('21m00Tcm4TlvDq8ikWAM')
        ->withSpeed(1.2)
        ->withLanguage('en')
        ->asAudio();

    $decoded = base64_decode($r->data);
    assert_true(strlen($decoded) > 500, 'Audio data should exist');

    saveAudio('tts-speed-language', $r->data, $r->format);
});

test('TTS with voice settings', function () {
    $r = Atlas::audio(Provider::ElevenLabs, 'eleven_multilingual_v2')
        ->instructions('Testing voice settings with stability and similarity boost.')
        ->withVoice('21m00Tcm4TlvDq8ikWAM')
        ->withProviderOptions([
            'stability' => 0.7,
            'similarity_boost' => 0.8,
        ])
        ->asAudio();

    $decoded = base64_decode($r->data);
    assert_true(strlen($decoded) > 500, 'Audio data should exist');

    saveAudio('tts-voice-settings', $r->data, $r->format);
});

test('TTS uses default voice when none specified', function () {
    $r = Atlas::audio(Provider::ElevenLabs, 'eleven_multilingual_v2')
        ->instructions('Default voice test, no voice ID specified.')
        ->asAudio();

    $decoded = base64_decode($r->data);
    assert_true(strlen($decoded) > 500, 'Should generate audio with default voice');

    saveAudio('tts-default-voice', $r->data, $r->format);
});

// ── Sound Effects ────────────────────────────────────────────────────────────

echo "\n\n── Sound Effects";

test('basic SFX generation', function () {
    $r = Atlas::audio(Provider::ElevenLabs, 'eleven_text_to_sound_v2')
        ->instructions('Thunder rumbling in the distance')
        ->withMeta(['_audio_mode' => 'sfx'])
        ->withDuration(3)
        ->asAudio();

    $decoded = base64_decode($r->data);
    assert_true(strlen($decoded) > 1000, 'SFX audio should be substantial ('.strlen($decoded).' bytes)');
    assert_true($r->format === 'mp3', "Format should be mp3, got: {$r->format}");

    saveAudio('sfx-thunder', $r->data, $r->format);
    echo ' ('.round(strlen($decoded) / 1024).' KB)';
});

test('SFX with prompt influence', function () {
    $r = Atlas::audio(Provider::ElevenLabs, 'eleven_text_to_sound_v2')
        ->instructions('Gentle rain on a window')
        ->withMeta(['_audio_mode' => 'sfx'])
        ->withDuration(5)
        ->withProviderOptions([
            'prompt_influence' => 0.7,
        ])
        ->asAudio();

    $decoded = base64_decode($r->data);
    assert_true(strlen($decoded) > 500, 'SFX with prompt influence should generate audio');

    saveAudio('sfx-rain', $r->data, $r->format);
});

test('SFX looping sound', function () {
    $r = Atlas::audio(Provider::ElevenLabs, 'eleven_text_to_sound_v2')
        ->instructions('Forest birds chirping ambient loop')
        ->withMeta(['_audio_mode' => 'sfx'])
        ->withDuration(5)
        ->withProviderOptions([
            'loop' => true,
        ])
        ->asAudio();

    $decoded = base64_decode($r->data);
    assert_true(strlen($decoded) > 500, 'Looping SFX should generate audio');

    saveAudio('sfx-birds-loop', $r->data, $r->format);
});

// ── Music Generation ─────────────────────────────────────────────────────────

echo "\n\n── Music Generation";

test('basic music generation', function () {
    $r = Atlas::audio(Provider::ElevenLabs, 'music_v1')
        ->instructions('Soft ambient piano, slow tempo, calm mood')
        ->withMeta(['_audio_mode' => 'music'])
        ->withDuration(5)
        ->asAudio();

    $decoded = base64_decode($r->data);
    assert_true(strlen($decoded) > 5000, 'Music audio should be substantial ('.strlen($decoded).' bytes)');
    assert_true($r->format === 'mp3', "Format should be mp3, got: {$r->format}");

    saveAudio('music-ambient-piano', $r->data, $r->format);
    echo ' ('.round(strlen($decoded) / 1024).' KB)';
});

// ── Speech-to-Text ───────────────────────────────────────────────────────────

echo "\n\n── Speech-to-Text (round-trip)";

test('TTS then STT round-trip', function () {
    // Step 1: Generate TTS audio
    $ttsResponse = Atlas::audio(Provider::ElevenLabs, 'eleven_multilingual_v2')
        ->instructions('The quick brown fox jumps over the lazy dog.')
        ->withVoice('21m00Tcm4TlvDq8ikWAM')
        ->asAudio();

    $audioData = base64_decode($ttsResponse->data);
    assert_true(strlen($audioData) > 500, 'TTS should produce audio');

    saveAudio('stt-input-tts', $ttsResponse->data, $ttsResponse->format);

    // Step 2: Save to temp file and transcribe
    $tmpPath = tempnam(sys_get_temp_dir(), 'atlas_stt_').'.mp3';
    file_put_contents($tmpPath, $audioData);

    try {
        $sttResponse = Atlas::audio(Provider::ElevenLabs, 'scribe_v2')
            ->withMedia([Audio::fromPath($tmpPath)])
            ->asText();

        assert_true(strlen($sttResponse->text) > 0, 'Transcript should not be empty');

        // Check that key words appear in the transcript
        $text = strtolower($sttResponse->text);
        $hasKeyWords = str_contains($text, 'fox') || str_contains($text, 'dog') || str_contains($text, 'quick');
        assert_true($hasKeyWords, "Transcript should contain key words from input, got: {$sttResponse->text}");

        // Save transcript to storage
        global $storageDir;
        file_put_contents("{$storageDir}/stt-transcript.txt", $sttResponse->text);

        echo " → \"{$sttResponse->text}\"";
    } finally {
        unlink($tmpPath);
    }
});

// ─── Summary ─────────────────────────────────────────────────────────────────

echo "\n\n══════════════════════════════════════════════";
echo "\n  Results: {$passed} passed, {$failed} failed, {$skipped} skipped";
echo "\n  Audio files: {$storageDir}/";
echo "\n══════════════════════════════════════════════\n";

if ($errors !== []) {
    echo "\nFailures:\n";

    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
}

echo "\n";

exit($failed > 0 ? 1 : 0);
