<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Tools;

/**
 * Abstract base for provider-native tools.
 *
 * Provider tools are configuration objects — not Atlas tools. They have no handle() method.
 * The provider executes them natively; ToolMapper converts them to provider format.
 */
abstract class ProviderTool
{
    /**
     * Provider tool type identifier (e.g. 'web_search', 'code_interpreter').
     */
    abstract public function type(): string;

    /**
     * Tool-specific configuration.
     *
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return [];
    }

    /**
     * Array format for ToolMapper::mapProviderTools().
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge(
            ['type' => $this->type()],
            $this->config(),
        );
    }
}
