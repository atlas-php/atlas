<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Enums;

/**
 * Normalized lifecycle status for a provider batch job.
 *
 * Providers each use their own vocabulary (OpenAI `validating/in_progress/…`,
 * Anthropic `in_progress/ended`, Gemini `JOB_STATE_*`, xAI counters). Each batch
 * handler maps its native status into this enum so consumers and the poller
 * reason about one set of states.
 */
enum BatchStatus: string
{
    case Validating = 'validating';
    case InProgress = 'in_progress';
    case Finalizing = 'finalizing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelling = 'cancelling';
    case Cancelled = 'cancelled';

    /**
     * Whether the job has reached a state that will not change further.
     *
     * Terminal jobs are skipped by the poller and are safe to hydrate once.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Failed, self::Expired, self::Cancelled => true,
            self::Validating, self::InProgress, self::Finalizing, self::Cancelling => false,
        };
    }

    /**
     * Whether the job finished successfully and its results can be ingested.
     */
    public function isSuccessful(): bool
    {
        return $this === self::Completed;
    }
}
