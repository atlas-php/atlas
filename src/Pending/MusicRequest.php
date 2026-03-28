<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending;

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Illuminate\Broadcasting\Channel;

/**
 * Fluent builder for music generation requests.
 */
class MusicRequest extends GenerativeAudioRequest
{
    protected function modality(): Modality
    {
        return Modality::Music;
    }

    protected function audioMode(): string
    {
        return 'music';
    }

    /**
     * Resolve the execution type — always Music regardless of terminal.
     */
    protected function resolveExecutionType(string $terminal): ExecutionType
    {
        return ExecutionType::Music;
    }

    /**
     * Rebuild this request from a queue payload and execute the given terminal method.
     *
     * @param  array<string, mixed>  $payload
     * @param  string  $terminal  Terminal method name (e.g. 'asAudio')
     * @param  int|null  $executionId  Pre-created execution ID for persistence
     * @param  Channel|null  $broadcastChannel  Channel for broadcasting
     */
    public static function executeFromPayload(
        array $payload,
        string $terminal,
        ?int $executionId = null,
        ?Channel $broadcastChannel = null,
    ): mixed {
        $request = Atlas::music($payload['provider'], $payload['model']);

        static::applyPayload($request, $payload, $executionId);

        return match ($terminal) {
            'asAudio' => $request->asAudio(),
            default => throw new \InvalidArgumentException("Unknown terminal method: {$terminal}"),
        };
    }
}
