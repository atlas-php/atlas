<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending;

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Illuminate\Broadcasting\Channel;

/**
 * Fluent builder for sound effect generation requests.
 */
class SfxRequest extends GenerativeAudioRequest
{
    protected function modality(): Modality
    {
        return Modality::Sfx;
    }

    protected function audioMode(): string
    {
        return 'sfx';
    }

    /**
     * Resolve the execution type — always Sfx regardless of terminal.
     */
    protected function resolveExecutionType(string $terminal): ExecutionType
    {
        return ExecutionType::Sfx;
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
        $request = Atlas::sfx($payload['provider'], $payload['model']);

        static::applyPayload($request, $payload, $executionId);

        return match ($terminal) {
            'asAudio' => $request->asAudio(),
            default => throw new \InvalidArgumentException("Unknown terminal method: {$terminal}"),
        };
    }
}
