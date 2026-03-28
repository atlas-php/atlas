<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Xai\Handlers;

use Atlasphp\Atlas\Providers\Handlers\AbstractProviderHandler;
use Atlasphp\Atlas\Providers\VoiceList;

/**
 * xAI provider handler for metadata endpoints.
 *
 * Fetches voices from xAI's /v1/tts/voices endpoint.
 */
class Provider extends AbstractProviderHandler
{
    protected function fetchVoices(): VoiceList
    {
        $data = $this->http->get(
            url: "{$this->config->baseUrl}/tts/voices",
            headers: $this->headersWithoutContentType(),
            timeout: $this->config->timeout,
        );

        /** @var array<int, array<string, mixed>> $voices */
        $voices = $data['voices'] ?? [];

        $ids = array_map(fn (array $voice): string => (string) $voice['voice_id'], $voices);

        sort($ids);

        return new VoiceList($ids);
    }
}
