<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Enums\VoiceCallStatus;
use Atlasphp\Atlas\Persistence\Http\StoreVoiceTranscriptController;
use Atlasphp\Atlas\Persistence\Models\ConversationMessage;
use Atlasphp\Atlas\Persistence\Models\VoiceCall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

// ─── Helpers ─────────────────────────────────────────────────────

function invokeTranscriptController(string $sessionId, array $body): JsonResponse
{
    $controller = app(StoreVoiceTranscriptController::class);
    $request = Request::create("/voice/{$sessionId}/transcript", 'POST', $body);
    $request->setRouteResolver(fn () => null);

    return $controller($request, $sessionId);
}

// ─── Tests ──────────────────────────────────────────────────────

it('saves transcript turns to VoiceCall record', function () {
    $voiceCall = VoiceCall::create([
        'voice_session_id' => 'sess-transcript-1',
        'provider' => 'xai',
        'model' => 'grok-3',
        'status' => VoiceCallStatus::Active,
        'transcript' => [],
        'started_at' => now(),
    ]);

    $response = invokeTranscriptController('sess-transcript-1', [
        'turns' => [
            ['role' => 'user', 'content' => 'Hello there'],
            ['role' => 'assistant', 'content' => 'Hi! How can I help?'],
        ],
    ]);

    expect($response->getStatusCode())->toBe(200);

    $voiceCall->refresh();
    expect($voiceCall->transcript)->toHaveCount(2);
    expect($voiceCall->transcript[0]['role'])->toBe('user');
    expect($voiceCall->transcript[0]['content'])->toBe('Hello there');
    expect($voiceCall->transcript[1]['role'])->toBe('assistant');
    expect($voiceCall->transcript[1]['content'])->toBe('Hi! How can I help?');
});

it('replaces transcript atomically on subsequent calls', function () {
    $voiceCall = VoiceCall::create([
        'voice_session_id' => 'sess-replace-1',
        'provider' => 'xai',
        'model' => 'grok-3',
        'status' => VoiceCallStatus::Active,
        'transcript' => [['role' => 'user', 'content' => 'Old text']],
        'started_at' => now(),
    ]);

    // Second call replaces the entire transcript
    invokeTranscriptController('sess-replace-1', [
        'turns' => [
            ['role' => 'user', 'content' => 'Full updated transcript'],
            ['role' => 'assistant', 'content' => 'Got it!'],
        ],
    ]);

    $voiceCall->refresh();
    expect($voiceCall->transcript)->toHaveCount(2);
    expect($voiceCall->transcript[0]['content'])->toBe('Full updated transcript');
});

it('returns 404 when voice call not found', function () {
    $response = invokeTranscriptController('nonexistent-session', [
        'turns' => [
            ['role' => 'user', 'content' => 'Hello'],
        ],
    ]);

    expect($response->getStatusCode())->toBe(404);
});

it('validates turns are required', function () {
    VoiceCall::create([
        'voice_session_id' => 'sess-validate-1',
        'provider' => 'xai',
        'model' => 'grok-3',
        'status' => VoiceCallStatus::Active,
        'transcript' => [],
        'started_at' => now(),
    ]);

    invokeTranscriptController('sess-validate-1', []);
})->throws(ValidationException::class);

it('validates turn content is required', function () {
    VoiceCall::create([
        'voice_session_id' => 'sess-validate-2',
        'provider' => 'xai',
        'model' => 'grok-3',
        'status' => VoiceCallStatus::Active,
        'transcript' => [],
        'started_at' => now(),
    ]);

    invokeTranscriptController('sess-validate-2', [
        'turns' => [
            ['role' => 'user', 'content' => ''],
        ],
    ]);
})->throws(ValidationException::class);

it('does not create any messages in the messages table', function () {
    VoiceCall::create([
        'voice_session_id' => 'sess-no-messages',
        'provider' => 'xai',
        'model' => 'grok-3',
        'status' => VoiceCallStatus::Active,
        'transcript' => [],
        'started_at' => now(),
    ]);

    invokeTranscriptController('sess-no-messages', [
        'turns' => [
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi'],
        ],
    ]);

    expect(ConversationMessage::count())->toBe(0);
});
