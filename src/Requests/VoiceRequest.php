<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Requests;

use Atlasphp\Atlas\Enums\TurnDetectionMode;
use Atlasphp\Atlas\Enums\VoiceTransport;

/**
 * Immutable request object for voice-to-voice sessions.
 */
final class VoiceRequest
{
    /**
     * @param  array<int, mixed>  $tools
     * @param  array<string, mixed>  $providerOptions
     * @param  array<int, mixed>  $middleware
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $model,
        public readonly ?string $instructions,
        public readonly ?string $voice,
        public readonly VoiceTransport $transport = VoiceTransport::WebSocket,
        public readonly TurnDetectionMode $turnDetection = TurnDetectionMode::ServerVad,
        public readonly ?float $vadThreshold = null,
        public readonly ?int $vadSilenceDuration = null,
        public readonly ?string $inputAudioFormat = null,
        public readonly ?string $outputAudioFormat = null,
        public readonly ?float $temperature = null,
        public readonly ?int $maxResponseTokens = null,
        public readonly ?string $inputAudioTranscription = null,
        public readonly array $tools = [],
        public readonly array $providerOptions = [],
        public readonly array $middleware = [],
        public readonly array $meta = [],
    ) {}
}
