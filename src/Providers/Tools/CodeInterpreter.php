<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Tools;

/**
 * Code interpreter provider tool configuration (OpenAI Responses API).
 *
 * OpenAI requires a `container` on this tool; it defaults to an auto-provisioned
 * container so the tool works out of the box. Pass `container` in the options
 * bag to pin a specific container id or other settings.
 */
class CodeInterpreter extends ProviderTool
{
    public function type(): string
    {
        return 'code_interpreter';
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return array_merge(
            ['container' => ['type' => 'auto']],
            $this->options,
        );
    }
}
