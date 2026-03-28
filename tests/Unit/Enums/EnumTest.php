<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Enums\Role;

it('resolves Provider enum from string values', function () {
    expect(Provider::from('openai'))->toBe(Provider::OpenAI);
    expect(Provider::from('anthropic'))->toBe(Provider::Anthropic);
    expect(Provider::from('google'))->toBe(Provider::Google);
    expect(Provider::from('xai'))->toBe(Provider::xAI);
});

it('resolves Role enum from string values', function () {
    expect(Role::from('system'))->toBe(Role::System);
    expect(Role::from('user'))->toBe(Role::User);
    expect(Role::from('assistant'))->toBe(Role::Assistant);
    expect(Role::from('tool'))->toBe(Role::Tool);
});

it('resolves FinishReason enum from string values', function () {
    expect(FinishReason::from('stop'))->toBe(FinishReason::Stop);
    expect(FinishReason::from('length'))->toBe(FinishReason::Length);
    expect(FinishReason::from('tool_calls'))->toBe(FinishReason::ToolCalls);
    expect(FinishReason::from('content_filter'))->toBe(FinishReason::ContentFilter);
});

it('resolves ChunkType enum from string values', function () {
    expect(ChunkType::from('text'))->toBe(ChunkType::Text);
    expect(ChunkType::from('thinking'))->toBe(ChunkType::Thinking);
    expect(ChunkType::from('tool_call'))->toBe(ChunkType::ToolCall);
    expect(ChunkType::from('done'))->toBe(ChunkType::Done);
});

it('normalizes Provider enum to string', function () {
    expect(Provider::normalize(Provider::OpenAI))->toBe('openai');
    expect(Provider::normalize(Provider::Anthropic))->toBe('anthropic');
    expect(Provider::normalize(Provider::Google))->toBe('google');
    expect(Provider::normalize(Provider::xAI))->toBe('xai');
});

it('normalizes Provider string passthrough', function () {
    expect(Provider::normalize('openai'))->toBe('openai');
    expect(Provider::normalize('custom-provider'))->toBe('custom-provider');
});

it('returns null for invalid enum values', function () {
    expect(Provider::tryFrom('invalid'))->toBeNull();
    expect(Role::tryFrom('invalid'))->toBeNull();
    expect(FinishReason::tryFrom('invalid'))->toBeNull();
    expect(ChunkType::tryFrom('invalid'))->toBeNull();
});
