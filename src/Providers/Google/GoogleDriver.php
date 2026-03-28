<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Google;

use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\Handlers\EmbedHandler;
use Atlasphp\Atlas\Providers\Handlers\ImageHandler;
use Atlasphp\Atlas\Providers\Handlers\ProviderHandler;
use Atlasphp\Atlas\Providers\Handlers\TextHandler;
use Atlasphp\Atlas\Providers\ProviderCapabilities;

/**
 * Google Gemini provider driver.
 *
 * Supports text, streaming, structured output, image generation, embeddings,
 * vision, tool calling, provider tools (Google Search, Code Execution), and models.
 */
class GoogleDriver extends Driver
{
    public function name(): string
    {
        return 'google';
    }

    public function capabilities(): ProviderCapabilities
    {
        return ProviderCapabilities::withOverrides(
            new ProviderCapabilities(
                text: true,
                stream: true,
                structured: true,
                image: true,
                imageToText: false,
                embed: true,
                vision: true,
                toolCalling: true,
                providerTools: true,
                models: true,
            ),
            $this->config->capabilityOverrides,
        );
    }

    protected function textHandler(): TextHandler
    {
        $toolMapper = new ToolMapper;

        return new Handlers\Text(
            config: $this->config,
            http: $this->http,
            messages: new MessageFactory,
            media: new MediaResolver,
            tools: $toolMapper,
            parser: new ResponseParser($toolMapper),
        );
    }

    protected function imageHandler(): ImageHandler
    {
        return new Handlers\Image($this->config, $this->http);
    }

    protected function embedHandler(): EmbedHandler
    {
        return new Handlers\Embed($this->config, $this->http);
    }

    protected function providerHandler(string $feature = 'provider'): ProviderHandler
    {
        return new Handlers\Provider($this->config, $this->http, $this->cache);
    }
}
