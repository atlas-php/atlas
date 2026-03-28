<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Enums;

/**
 * Lifecycle status for executions, steps, and tool calls.
 *
 * Int-backed for tinyint storage — compact and fast indexed queries.
 * Shared across all three execution tables.
 */
enum ExecutionStatus: int
{
    case Pending = 0;
    case Queued = 1;
    case Processing = 2;
    case Completed = 3;
    case Failed = 4;

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed]);
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::Queued, self::Processing]);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Queued => 'Queued',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }
}
