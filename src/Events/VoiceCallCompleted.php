<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

/**
 * Dispatched when a voice call completes with the full transcript.
 *
 * Consumers can listen for this event to:
 * - Generate a summary and store it on the VoiceCall
 * - Embed transcript content into agent memory
 * - Inject a summary message into the conversation
 * - Log analytics or send notifications
 */
class VoiceCallCompleted
{
    /**
     * @param  array<int, array{role: string, content: string}>  $transcript
     */
    public function __construct(
        public readonly int $voiceCallId,
        public readonly ?int $conversationId,
        public readonly string $sessionId,
        public readonly array $transcript,
        public readonly ?int $durationMs,
    ) {}
}
