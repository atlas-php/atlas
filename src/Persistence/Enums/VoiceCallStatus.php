<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Enums;

/**
 * Lifecycle status for voice calls.
 *
 * String-backed to match the varchar column in the voice_calls table.
 */
enum VoiceCallStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed]);
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }
}
