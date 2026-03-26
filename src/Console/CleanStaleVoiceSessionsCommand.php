<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Console;

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Persistence\Enums\VoiceCallStatus;
use Atlasphp\Atlas\Persistence\Models\VoiceCall;
use Atlasphp\Atlas\Persistence\Services\ExecutionService;
use Illuminate\Console\Command;

/**
 * Cleans up stale voice calls that were never completed.
 *
 * Voice calls are marked active when created and completed when the browser
 * sends a close request. If the browser disconnects without closing, the
 * call stays active indefinitely. This command sweeps those stale records.
 *
 * Schedule: $schedule->command('atlas:clean-voice-sessions')->hourly();
 */
class CleanStaleVoiceSessionsCommand extends Command
{
    protected $signature = 'atlas:clean-voice-sessions
        {--ttl= : Minutes before an active voice call is considered stale (default: config value)}';

    protected $description = 'Clean up stale voice calls and their executions';

    public function handle(ExecutionService $executionService): int
    {
        if (! app(AtlasConfig::class)->persistenceEnabled) {
            $this->info('Persistence is not enabled. Nothing to clean.');

            return self::SUCCESS;
        }

        $ttl = (int) ($this->option('ttl') ?? app(AtlasConfig::class)->voiceSessionTtl);

        /** @var class-string<VoiceCall> $voiceCallModel */
        $voiceCallModel = app(AtlasConfig::class)->model('voice_call', VoiceCall::class);

        $cutoff = now()->subMinutes($ttl);

        $stale = $voiceCallModel::where('status', VoiceCallStatus::Active)
            ->where('started_at', '<', $cutoff)
            ->get();

        if ($stale->isEmpty()) {
            $this->info('No stale voice calls found.');

            return self::SUCCESS;
        }

        foreach ($stale as $call) {
            $call->markCompleted($call->transcript ?? []);

            // Complete any linked execution (VoiceCall owns execution_id FK)
            if ($call->execution_id !== null) {
                $executionService->completeVoiceExecution($call->execution_id, ['stale_cleanup' => true]);
            }
        }

        $this->info("Cleaned {$stale->count()} stale voice call(s).");

        return self::SUCCESS;
    }
}
