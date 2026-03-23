<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Concerns;

use Atlasphp\Atlas\Requests\VoiceRequest;

/**
 * Shared session body construction for voice provider handlers.
 *
 * Expects the using class to handle provider-specific differences
 * (e.g., default voice) via the $defaultVoice parameter.
 */
trait BuildsVoiceBody
{
    /**
     * Build the session configuration body from a VoiceRequest.
     *
     * @return array<string, mixed>
     */
    private function buildSessionBody(VoiceRequest $request, ?string $defaultVoice = null): array
    {
        $body = array_filter([
            'model' => $request->model,
            'voice' => $request->voice ?? $defaultVoice,
            'instructions' => $request->instructions,
            'modalities' => ['text', 'audio'],
            'input_audio_format' => $request->inputAudioFormat ?? 'pcm16',
            'output_audio_format' => $request->outputAudioFormat ?? 'pcm16',
            'temperature' => $request->temperature,
            'max_response_output_tokens' => $request->maxResponseTokens,
        ], fn (mixed $v): bool => $v !== null);

        $body['turn_detection'] = $this->buildTurnDetection($request);

        if ($request->tools !== []) {
            $body['tools'] = $request->tools;
        }

        if ($request->inputAudioTranscription !== null) {
            $body['input_audio_transcription'] = [
                'model' => $request->inputAudioTranscription,
            ];
        }

        return array_merge($body, $request->providerOptions);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTurnDetection(VoiceRequest $request): array
    {
        $td = ['type' => $request->turnDetection->value];

        if ($request->vadThreshold !== null) {
            $td['threshold'] = $request->vadThreshold;
        }

        if ($request->vadSilenceDuration !== null) {
            $td['silence_duration_ms'] = $request->vadSilenceDuration;
        }

        return $td;
    }

    /**
     * Convert an HTTPS base URL to a WebSocket URL.
     */
    private function toWebSocketUrl(string $baseUrl): string
    {
        return str_replace('https://', 'wss://', rtrim($baseUrl, '/'));
    }
}
