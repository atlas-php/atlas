<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Enums\AssetType;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Persistence\Enums\MessageRole;
use Atlasphp\Atlas\Persistence\Enums\MessageStatus;
use Atlasphp\Atlas\Persistence\Enums\ToolCallType;
use Atlasphp\Atlas\Persistence\Enums\VoiceCallStatus;

// ─── ExecutionType ──────────────────────────────────────────────

it('producesFile returns true for Image, Audio, Video', function (ExecutionType $type) {
    expect($type->producesFile())->toBeTrue();
})->with([
    ExecutionType::Image,
    ExecutionType::Audio,
    ExecutionType::Video,
]);

it('producesFile returns false for Text, Structured, Embed, etc.', function (ExecutionType $type) {
    expect($type->producesFile())->toBeFalse();
})->with([
    ExecutionType::Text,
    ExecutionType::Structured,
    ExecutionType::Stream,
    ExecutionType::ImageToText,
    ExecutionType::AudioToText,
    ExecutionType::VideoToText,
    ExecutionType::Embed,
    ExecutionType::Moderate,
    ExecutionType::Rerank,
]);

it('assetType returns correct AssetType for file types', function (ExecutionType $type, AssetType $expected) {
    expect($type->assetType())->toBe($expected);
})->with([
    [ExecutionType::Image, AssetType::Image],
    [ExecutionType::Audio, AssetType::Audio],
    [ExecutionType::Video, AssetType::Video],
]);

it('assetType returns null for non-file types', function (ExecutionType $type) {
    expect($type->assetType())->toBeNull();
})->with([
    ExecutionType::Text,
    ExecutionType::Structured,
    ExecutionType::Stream,
    ExecutionType::ImageToText,
    ExecutionType::AudioToText,
    ExecutionType::VideoToText,
    ExecutionType::Embed,
    ExecutionType::Moderate,
    ExecutionType::Rerank,
]);

it('fromDriverMethod maps all driver methods correctly', function (string $method, ExecutionType $expected) {
    expect(ExecutionType::fromDriverMethod($method))->toBe($expected);
})->with([
    ['text', ExecutionType::Text],
    ['structured', ExecutionType::Structured],
    ['stream', ExecutionType::Stream],
    ['image', ExecutionType::Image],
    ['imageToText', ExecutionType::ImageToText],
    ['audio', ExecutionType::Audio],
    ['audioToText', ExecutionType::AudioToText],
    ['video', ExecutionType::Video],
    ['videoToText', ExecutionType::VideoToText],
    ['embed', ExecutionType::Embed],
    ['moderate', ExecutionType::Moderate],
    ['rerank', ExecutionType::Rerank],
]);

it('fromDriverMethod throws ValueError for unknown method', function () {
    ExecutionType::fromDriverMethod('nonexistent');
})->throws(ValueError::class, 'Unknown driver method: nonexistent');

// ─── ExecutionStatus ────────────────────────────────────────────

it('isTerminal returns true for Completed and Failed', function (ExecutionStatus $status) {
    expect($status->isTerminal())->toBeTrue();
})->with([
    ExecutionStatus::Completed,
    ExecutionStatus::Failed,
]);

it('isTerminal returns false for Pending, Queued, Processing', function (ExecutionStatus $status) {
    expect($status->isTerminal())->toBeFalse();
})->with([
    ExecutionStatus::Pending,
    ExecutionStatus::Queued,
    ExecutionStatus::Processing,
]);

it('isActive returns true for Pending, Queued, Processing', function (ExecutionStatus $status) {
    expect($status->isActive())->toBeTrue();
})->with([
    ExecutionStatus::Pending,
    ExecutionStatus::Queued,
    ExecutionStatus::Processing,
]);

it('isActive returns false for Completed and Failed', function (ExecutionStatus $status) {
    expect($status->isActive())->toBeFalse();
})->with([
    ExecutionStatus::Completed,
    ExecutionStatus::Failed,
]);

it('label returns human-readable string for each case', function (ExecutionStatus $status, string $expected) {
    expect($status->label())->toBe($expected);
})->with([
    [ExecutionStatus::Pending, 'Pending'],
    [ExecutionStatus::Queued, 'Queued'],
    [ExecutionStatus::Processing, 'Processing'],
    [ExecutionStatus::Completed, 'Completed'],
    [ExecutionStatus::Failed, 'Failed'],
]);

// ─── AssetType ──────────────────────────────────────────────────

it('isMedia returns true for Image, Audio, Video', function (AssetType $type) {
    expect($type->isMedia())->toBeTrue();
})->with([
    AssetType::Image,
    AssetType::Audio,
    AssetType::Video,
]);

it('isMedia returns false for Document, Text, Json, File', function (AssetType $type) {
    expect($type->isMedia())->toBeFalse();
})->with([
    AssetType::Document,
    AssetType::Text,
    AssetType::Json,
    AssetType::File,
]);

// ─── MessageRole ───────────────────────────────────────────────

it('MessageRole has correct case values', function (string $value) {
    expect(MessageRole::from($value))->toBeInstanceOf(MessageRole::class);
})->with(['user', 'assistant', 'system']);

// ─── MessageStatus ─────────────────────────────────────────────

it('MessageStatus has correct case values', function (string $value) {
    expect(MessageStatus::from($value))->toBeInstanceOf(MessageStatus::class);
})->with(['delivered', 'queued']);

// ─── ToolCallType ──────────────────────────────────────────────

it('ToolCallType has correct case values', function (string $value) {
    expect(ToolCallType::from($value))->toBeInstanceOf(ToolCallType::class);
})->with(['local', 'mcp', 'provider']);

// ─── VoiceCallStatus ──────────────────────────────────────────

it('VoiceCallStatus has correct case values', function (string $value) {
    expect(VoiceCallStatus::from($value))->toBeInstanceOf(VoiceCallStatus::class);
})->with(['active', 'completed', 'failed']);

it('VoiceCallStatus isTerminal returns true for Completed and Failed', function (VoiceCallStatus $status) {
    expect($status->isTerminal())->toBeTrue();
})->with([
    VoiceCallStatus::Completed,
    VoiceCallStatus::Failed,
]);

it('VoiceCallStatus isTerminal returns false for Active', function () {
    expect(VoiceCallStatus::Active->isTerminal())->toBeFalse();
});

it('VoiceCallStatus label returns human-readable string for each case', function (VoiceCallStatus $status, string $expected) {
    expect($status->label())->toBe($expected);
})->with([
    [VoiceCallStatus::Active, 'Active'],
    [VoiceCallStatus::Completed, 'Completed'],
    [VoiceCallStatus::Failed, 'Failed'],
]);
