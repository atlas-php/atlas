<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Xai;

use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\Handlers\AudioHandler;
use Atlasphp\Atlas\Providers\Handlers\ImageHandler;
use Atlasphp\Atlas\Providers\Handlers\ProviderHandler;
use Atlasphp\Atlas\Providers\Handlers\RealtimeHandler;
use Atlasphp\Atlas\Providers\Handlers\TextHandler;
use Atlasphp\Atlas\Providers\Handlers\VideoHandler;
use Atlasphp\Atlas\Providers\OpenAi\Handlers\Image;
use Atlasphp\Atlas\Providers\OpenAi\MediaResolver;
use Atlasphp\Atlas\Providers\OpenAi\ResponseParser;
use Atlasphp\Atlas\Providers\OpenAi\ToolMapper;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Providers\Xai\Handlers\Audio;
use Atlasphp\Atlas\Providers\Xai\Handlers\Provider;
use Atlasphp\Atlas\Providers\Xai\Handlers\Realtime;
use Atlasphp\Atlas\Providers\Xai\Handlers\Text;
use Atlasphp\Atlas\Providers\Xai\Handlers\Video;

/**
 * xAI (Grok) provider driver using the Responses API.
 *
 * Reuses OpenAI's MediaResolver, ToolMapper, and ResponseParser since xAI
 * uses the same wire format. Custom components handle xAI-specific differences:
 * instructions as system messages, TTS via /v1/tts, and async video generation.
 */
class XaiDriver extends Driver
{
    public function name(): string
    {
        return 'xai';
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            text: true,
            stream: true,
            structured: true,
            image: true,
            imageToText: false,
            audio: true,
            audioToText: false,
            video: true,
            videoToText: false,
            embed: false,
            moderate: false,
            realtime: true,
            vision: true,
            toolCalling: true,
            providerTools: true,
            models: true,
            voices: true,
        );
    }

    protected function providerHandler(string $feature = 'provider'): ProviderHandler
    {
        return new Provider($this->config, $this->http, $this->cache);
    }

    protected function textHandler(): TextHandler
    {
        $toolMapper = new ToolMapper;

        return new Text(
            config: $this->config,
            http: $this->http,
            messages: new MessageFactory,
            media: new MediaResolver,
            toolMapper: $toolMapper,
            parser: new ResponseParser($toolMapper),
        );
    }

    protected function imageHandler(): ImageHandler
    {
        return new Image($this->config, $this->http);
    }

    protected function audioHandler(): AudioHandler
    {
        return new Audio($this->config, $this->http);
    }

    protected function videoHandler(): VideoHandler
    {
        return new Video($this->config, $this->http);
    }

    protected function realtimeHandler(): RealtimeHandler
    {
        return new Realtime($this->config, $this->http);
    }
}
