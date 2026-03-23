<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Requests;

use Atlasphp\Atlas\Enums\RealtimeTransport;
use Atlasphp\Atlas\Enums\TurnDetectionMode;

/**
 * Immutable request object for realtime voice-to-voice sessions.
 */
class RealtimeRequest
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
        public readonly RealtimeTransport $transport = RealtimeTransport::WebRtc,
        public readonly TurnDetectionMode $turnDetection = TurnDetectionMode::ServerVad,
        public readonly ?float $vadThreshold = null,
        public readonly ?int $vadSilenceDuration = null,
        public readonly ?string $inputAudioFormat = null,
        public readonly ?string $outputAudioFormat = null,
        public readonly ?float $temperature = null,
        public readonly ?int $maxResponseTokens = null,
        public readonly array $tools = [],
        public readonly array $providerOptions = [],
        public readonly array $middleware = [],
        public readonly array $meta = [],
    ) {}
}
