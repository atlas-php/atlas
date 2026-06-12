<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Anthropic;

use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\Handlers\BatchHandler;
use Atlasphp\Atlas\Providers\Handlers\ProviderHandler;
use Atlasphp\Atlas\Providers\Handlers\TextHandler;
use Atlasphp\Atlas\Providers\ProviderCapabilities;

/**
 * Anthropic Claude provider driver.
 *
 * Supports text, streaming, structured output, vision, tool calling, and models.
 */
class AnthropicDriver extends Driver
{
    public function name(): string
    {
        return 'anthropic';
    }

    public function capabilities(): ProviderCapabilities
    {
        return ProviderCapabilities::withOverrides(
            new ProviderCapabilities(
                text: true,
                stream: true,
                structured: true,
                vision: true,
                caching: true,
                toolCalling: true,
                providerTools: true,
                models: true,
                batch: true,
                batchModalities: ['text'],
            ),
            $this->config->capabilityOverrides,
        );
    }

    protected function textHandler(): TextHandler
    {
        return $this->buildTextHandler();
    }

    /**
     * Construct the Anthropic text handler, shared by the text and batch handlers.
     */
    private function buildTextHandler(): Handlers\Text
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

    protected function batchHandler(): BatchHandler
    {
        return new Handlers\Batch($this->config, $this->http, $this->buildTextHandler());
    }

    protected function providerHandler(string $feature = 'provider'): ProviderHandler
    {
        return new Handlers\Provider($this->config, $this->http, $this->cache);
    }
}
