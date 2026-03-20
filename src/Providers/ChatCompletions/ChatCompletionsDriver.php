<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\ChatCompletions;

use Atlasphp\Atlas\Providers\ChatCompletions\Handlers\Provider;
use Atlasphp\Atlas\Providers\ChatCompletions\Handlers\Text;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\Handlers\AudioHandler;
use Atlasphp\Atlas\Providers\Handlers\EmbedHandler;
use Atlasphp\Atlas\Providers\Handlers\ImageHandler;
use Atlasphp\Atlas\Providers\Handlers\ModerateHandler;
use Atlasphp\Atlas\Providers\Handlers\ProviderHandler;
use Atlasphp\Atlas\Providers\Handlers\TextHandler;
use Atlasphp\Atlas\Providers\Handlers\VideoHandler;
use Atlasphp\Atlas\Providers\OpenAi\Handlers\Audio;
use Atlasphp\Atlas\Providers\OpenAi\Handlers\Embed;
use Atlasphp\Atlas\Providers\OpenAi\Handlers\Image;
use Atlasphp\Atlas\Providers\OpenAi\Handlers\Moderate;
use Atlasphp\Atlas\Providers\OpenAi\Handlers\Video;
use Atlasphp\Atlas\Providers\ProviderCapabilities;

/**
 * Driver for providers using the Chat Completions wire format.
 *
 * Only the text handler differs from OpenAI (uses /v1/chat/completions).
 * All other modalities (image, audio, video, embed, moderate) reuse
 * OpenAI's handlers since those endpoints are standardized.
 *
 * Consumers disable unsupported modalities via config capability overrides.
 */
class ChatCompletionsDriver extends Driver
{
    public function name(): string
    {
        return 'chat_completions';
    }

    public function capabilities(): ProviderCapabilities
    {
        return ProviderCapabilities::withOverrides(
            new ProviderCapabilities(
                text: true,
                stream: true,
                structured: true,
                image: true,
                audio: true,
                audioToText: true,
                video: true,
                embed: true,
                moderate: true,
                vision: true,
                toolCalling: true,
                models: true,
            ),
            $this->config->capabilityOverrides,
        );
    }

    protected function providerHandler(string $feature = 'provider'): ProviderHandler
    {
        return new Provider($this->config, $this->http);
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

    protected function embedHandler(): EmbedHandler
    {
        return new Embed($this->config, $this->http);
    }

    protected function moderateHandler(): ModerateHandler
    {
        return new Moderate($this->config, $this->http);
    }
}
