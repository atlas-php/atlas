<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\ElevenLabs;

use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\Handlers\AudioHandler;
use Atlasphp\Atlas\Providers\Handlers\ProviderHandler;
use Atlasphp\Atlas\Providers\Handlers\VoiceHandler;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Requests\AudioRequest;
use Atlasphp\Atlas\Responses\AudioResponse;
use Atlasphp\Atlas\Responses\TextResponse;

/**
 * Class ElevenLabsDriver
 *
 * Audio and voice provider supporting TTS, STT, sound effects, music generation,
 * and Conversational AI voice sessions. Routes SFX and music through the audio
 * handler interface via _audio_mode dispatch.
 */
class ElevenLabsDriver extends Driver
{
    public function name(): string
    {
        return 'elevenlabs';
    }

    public function capabilities(): ProviderCapabilities
    {
        return ProviderCapabilities::withOverrides(
            new ProviderCapabilities(
                audio: true,
                audioToText: true,
                voice: true,
                models: true,
                voices: true,
            ),
            $this->config->capabilityOverrides,
        );
    }

    // ─── Audio routing with mode dispatch ────────────────────────────

    public function audio(AudioRequest $request): AudioResponse
    {
        return $this->dispatch('audio', $request, function (AudioRequest $req) {
            $mode = $req->meta['_audio_mode'] ?? 'tts';

            return match ($mode) {
                'sfx' => $this->sfxHandler()->audio($req),
                'music' => $this->musicHandler()->audio($req),
                default => $this->audioHandler()->audio($req),
            };
        });
    }

    public function audioToText(AudioRequest $request): TextResponse
    {
        return $this->dispatch('audioToText', $request, function (AudioRequest $req) {
            return $this->audioHandler()->audioToText($req);
        });
    }

    // ─── Handler Resolution ──────────────────────────────────────────

    protected function audioHandler(): AudioHandler
    {
        return new Handlers\Audio($this->config, $this->http);
    }

    protected function sfxHandler(): Handlers\Sfx
    {
        return new Handlers\Sfx($this->config, $this->http);
    }

    protected function musicHandler(): Handlers\Music
    {
        return new Handlers\Music($this->config, $this->http);
    }

    protected function voiceHandler(): VoiceHandler
    {
        return new Handlers\Voice($this->config, $this->http);
    }

    protected function providerHandler(string $feature = 'provider'): ProviderHandler
    {
        return new Handlers\Provider($this->config, $this->http, $this->cache);
    }
}
