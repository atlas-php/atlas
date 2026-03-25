<?php

declare(strict_types=1);

use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Persistence\Models\Asset;
use Atlasphp\Atlas\Persistence\Services\ExecutionService;
use Atlasphp\Atlas\Persistence\ToolAssets;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config()->set('atlas.storage.disk', 'local');
    config()->set('atlas.storage.prefix', 'atlas');
    config()->set('atlas.storage.visibility', 'private');
});

it('stores content to disk and creates asset record', function () {
    $asset = ToolAssets::store('file-content', [
        'type' => 'document',
        'mime_type' => 'text/csv',
    ]);

    expect($asset)->toBeInstanceOf(Asset::class)
        ->and($asset->exists)->toBeTrue()
        ->and($asset->type->value)->toBe('document')
        ->and($asset->mime_type)->toBe('text/csv')
        ->and($asset->size_bytes)->toBe(strlen('file-content'))
        ->and($asset->path)->toStartWith('atlas/assets/');

    Storage::disk('local')->assertExists($asset->path);
});

it('sets agent from active execution', function () {
    $service = app(ExecutionService::class);
    $service->createExecution(provider: 'openai', model: 'gpt-5', agent: 'test-agent');
    $service->beginExecution();

    $asset = ToolAssets::store('content', ['type' => 'document']);

    expect($asset->agent)->toBe('test-agent');
});

it('sets execution_id from active execution', function () {
    $service = app(ExecutionService::class);
    $execution = $service->createExecution(provider: 'openai', model: 'gpt-5', agent: 'test-agent');
    $service->beginExecution();

    $asset = ToolAssets::store('content', ['type' => 'document']);

    expect($asset->execution_id)->toBe($execution->id);
});

it('sets tool_call_id column from active tool call', function () {
    $service = app(ExecutionService::class);
    $service->createExecution(provider: 'openai', model: 'gpt-5', agent: 'test-agent');
    $service->beginExecution();
    $service->createStep();
    $service->beginStep();
    $service->createToolCall(new ToolCall('call_1', 'generate_csv', ['data' => 'test']));
    $service->beginToolCall($service->getCurrentToolCall());

    $asset = ToolAssets::store('csv-content', [
        'type' => 'document',
        'mime_type' => 'text/csv',
    ]);

    $toolCallRecord = $service->getCurrentToolCall();
    expect($asset->tool_call_id)->toBe($toolCallRecord->id)
        ->and($asset->toolCall)->not->toBeNull()
        ->and($asset->toolCall->name)->toBe('generate_csv');
});

it('works without active execution', function () {
    $asset = ToolAssets::store('content', ['type' => 'document']);

    expect($asset->exists)->toBeTrue()
        ->and($asset->agent)->toBeNull()
        ->and($asset->execution_id)->toBeNull();
});

it('works without active tool call', function () {
    $service = app(ExecutionService::class);
    $service->createExecution(provider: 'openai', model: 'gpt-5', agent: 'test-agent');
    $service->beginExecution();

    $asset = ToolAssets::store('content', ['type' => 'document']);

    expect($asset->tool_call_id)->toBeNull()
        ->and($asset->metadata)->toBeNull();
});

it('respects description from data', function () {
    $asset = ToolAssets::store('content', [
        'type' => 'document',
        'description' => 'Monthly sales report',
    ]);

    expect($asset->description)->toBe('Monthly sales report');
});

it('resolves extension from mime type', function () {
    $csvAsset = ToolAssets::store('csv', ['mime_type' => 'text/csv']);
    expect($csvAsset->filename)->toEndWith('.csv');

    $pngAsset = ToolAssets::store('png', ['mime_type' => 'image/png']);
    expect($pngAsset->filename)->toEndWith('.png');

    $pdfAsset = ToolAssets::store('pdf', ['mime_type' => 'application/pdf']);
    expect($pdfAsset->filename)->toEndWith('.pdf');

    $unknownAsset = ToolAssets::store('unknown', []);
    expect($unknownAsset->filename)->toEndWith('.bin');
});

it('resolves all supported mime type extensions', function (string $mimeType, string $extension) {
    $asset = ToolAssets::store('content', ['mime_type' => $mimeType]);
    expect($asset->filename)->toEndWith(".{$extension}");
})->with([
    'image/jpeg' => ['image/jpeg', 'jpg'],
    'image/gif' => ['image/gif', 'gif'],
    'image/webp' => ['image/webp', 'webp'],
    'image/svg+xml' => ['image/svg+xml', 'svg'],
    'audio/mpeg' => ['audio/mpeg', 'mp3'],
    'audio/wav' => ['audio/wav', 'wav'],
    'audio/ogg' => ['audio/ogg', 'ogg'],
    'audio/flac' => ['audio/flac', 'flac'],
    'video/mp4' => ['video/mp4', 'mp4'],
    'video/webm' => ['video/webm', 'webm'],
    'application/json' => ['application/json', 'json'],
    'text/plain' => ['text/plain', 'txt'],
]);

it('defaults type to file when not specified', function () {
    $asset = ToolAssets::store('content', []);

    expect($asset->type->value)->toBe('file');
});

it('lastStored returns null when no asset has been stored', function () {
    expect(ToolAssets::lastStored())->toBeNull();
});

it('lastStored delegates to ExecutionService getLastAsset', function () {
    $service = app(ExecutionService::class);

    // No execution → no last asset
    expect($service->getLastAsset())->toBeNull();
    expect(ToolAssets::lastStored())->toBeNull();
});
