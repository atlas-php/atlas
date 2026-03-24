<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Console;

use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Illuminate\Console\Command;

/**
 * Cleans up stale voice session executions that were never completed.
 *
 * Voice sessions are marked Processing when created and Completed when the
 * browser sends a transcript or close request. If the browser disconnects
 * without either signal, the execution stays Processing indefinitely.
 * This command sweeps those stale records.
 *
 * Schedule: $schedule->command('atlas:clean-voice-sessions')->hourly();
 */
class CleanStaleVoiceSessionsCommand extends Command
{
    protected $signature = 'atlas:clean-voice-sessions
        {--ttl= : Minutes before a Processing voice session is considered stale (default: config value)}';

    protected $description = 'Mark stale voice session executions as completed';

    public function handle(): int
    {
        if (! config('atlas.persistence.enabled')) {
            $this->info('Persistence is not enabled. Nothing to clean.');

            return self::SUCCESS;
        }

        $ttl = (int) ($this->option('ttl') ?? config('atlas.persistence.voice_session_ttl', 60));

        /** @var class-string<Execution> $executionModel */
        $executionModel = config('atlas.persistence.models.execution', Execution::class);

        $cutoff = now()->subMinutes($ttl);

        $stale = $executionModel::where('type', ExecutionType::Voice)
            ->where('status', ExecutionStatus::Processing)
            ->where('started_at', '<', $cutoff)
            ->get();

        if ($stale->isEmpty()) {
            $this->info('No stale voice sessions found.');

            return self::SUCCESS;
        }

        foreach ($stale as $execution) {
            if ($execution->voice_session_id !== null) {
                Execution::completeVoiceSession($execution->voice_session_id, ['stale_cleanup' => true]);
            }
        }

        $this->info("Cleaned {$stale->count()} stale voice session(s).");

        return self::SUCCESS;
    }
}
