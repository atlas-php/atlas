<?php

declare(strict_types=1);

namespace App\Listeners;

use Atlasphp\Atlas\Agents\AgentRegistry;
use Atlasphp\Atlas\Events\VoiceCallCompleted;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Persistence\Models\VoiceCall;

/**
 * Summarizes a completed voice call using a cheap xAI model
 * and stores the summary on the VoiceCall record.
 */
class SummarizeVoiceCall
{
    public function handle(VoiceCallCompleted $event): void
    {
        if ($event->transcript === []) {
            return;
        }

        $voiceCall = VoiceCall::find($event->voiceCallId);

        if ($voiceCall === null) {
            return;
        }

        // Format transcript for the summarizer
        $formatted = collect($event->transcript)
            ->map(fn (array $turn) => ucfirst($turn['role']).': '.$turn['content'])
            ->implode("\n");

        try {
            // Resolve the agent's display name from the registry
            $agentName = 'the assistant';

            if ($voiceCall->agent !== null) {
                try {
                    $agent = app(AgentRegistry::class)->resolve($voiceCall->agent);
                    $agentName = $agent->name();
                } catch (\Throwable) {
                    $agentName = $voiceCall->agent;
                }
            }

            $response = Atlas::text('xai', 'grok-3-mini-fast')
                ->instructions("You are {$agentName}, the assistant who just had this voice call. Write a brief first-person summary (2-3 sentences) of what was discussed. Use \"I\" and \"the user\" — e.g. \"I helped the user with...\". Focus on key topics, decisions made, important facts, and action items. No filler or greetings.")
                ->message($formatted)
                ->asText();

            $voiceCall->update(['summary' => $response->text]);
        } catch (\Throwable $e) {
            logger()->error('[SummarizeVoiceCall] Failed to summarize voice call', [
                'voice_call_id' => $event->voiceCallId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
