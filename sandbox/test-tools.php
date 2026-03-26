<?php

declare(strict_types=1);
use App\Models\User;
use App\Tools\GenerateImageTool;
use App\Tools\GenerateSpeechTool;
use App\Tools\GenerateVideoTool;
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Persistence\ToolAssets;

/**
 * Manual test script for sandbox tools.
 *
 * Usage: php sandbox/test-tools.php [image|video|speech|chat]
 */
$app = require __DIR__.'/bootstrap.php';

$tool = $argv[1] ?? 'image';

echo "Testing: {$tool}\n";
echo str_repeat('─', 50)."\n";

try {
    match ($tool) {
        'image' => testImage(),
        'video' => testVideo(),
        'speech' => testSpeech(),
        'chat' => testChat(),
        default => exit("Unknown tool: {$tool}\n"),
    };
} catch (Throwable $e) {
    echo "ERROR: {$e->getMessage()}\n";
    echo $e->getTraceAsString()."\n";
    exit(1);
}

function testImage(): void
{
    $tool = app(GenerateImageTool::class);

    echo "Calling generate_image...\n";

    $result = $tool->handle(
        ['prompt' => 'A simple blue circle on white background'],
        [],
    );

    echo "Result: {$result}\n";

    $asset = ToolAssets::lastStored();
    echo 'Asset ID: '.($asset?->id ?? 'null')."\n";
    echo 'Asset path: '.($asset?->path ?? 'null')."\n";
    echo 'Asset type: '.($asset?->type?->value ?? 'null')."\n";
}

function testSpeech(): void
{
    $tool = app(GenerateSpeechTool::class);

    echo "Calling generate_speech...\n";

    $result = $tool->handle(
        ['text' => 'Hello, this is a test of text to speech.'],
        [],
    );

    echo "Result: {$result}\n";

    $asset = ToolAssets::lastStored();
    echo 'Asset ID: '.($asset?->id ?? 'null')."\n";
    echo 'Asset path: '.($asset?->path ?? 'null')."\n";
    echo 'Asset type: '.($asset?->type?->value ?? 'null')."\n";
}

function testVideo(): void
{
    $tool = app(GenerateVideoTool::class);

    echo "Calling generate_video (this may take several minutes)...\n";

    $result = $tool->handle(
        ['prompt' => 'A simple rotating 3D cube', 'duration' => 5],
        [],
    );

    echo "Result: {$result}\n";

    $asset = ToolAssets::lastStored();
    echo 'Asset ID: '.($asset?->id ?? 'null')."\n";
    echo 'Asset path: '.($asset?->path ?? 'null')."\n";
    echo 'Asset type: '.($asset?->type?->value ?? 'null')."\n";
}

function testChat(): void
{
    echo "Running Atlas agent (text only, no tools)...\n";

    $user = User::findOrFail(1);

    $response = Atlas::agent('assistant')
        ->for($user)
        ->message('Say hello in one sentence.')
        ->asText();

    echo "Response: {$response->text}\n";
    echo 'Conversation ID: '.($response->meta['conversation_id'] ?? 'null')."\n";
    echo 'Execution ID: '.($response->meta['execution_id'] ?? 'null')."\n";
    echo "Tokens: {$response->usage->inputTokens} in / {$response->usage->outputTokens} out\n";
}
