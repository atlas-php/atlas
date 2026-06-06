<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Tools;

/**
 * Abstract base for provider-native tools.
 *
 * Provider tools are configuration objects — not Atlas tools. They have no handle() method.
 * The provider executes them natively; ToolMapper converts them to provider format.
 *
 * Every provider tool accepts an open `$options` bag that is merged into the
 * native request verbatim. Well-known attributes have typed constructor
 * parameters on the concrete tools (correct + documented), but the bag means a
 * provider attribute we don't model yet — anything the provider allows now or
 * adds later — can still be passed through without changing Atlas.
 */
abstract class ProviderTool
{
    /**
     * @param  array<string, mixed>  $options  Extra provider-native attributes merged into the request verbatim.
     */
    public function __construct(
        protected readonly array $options = [],
    ) {}

    /**
     * Provider tool type identifier (e.g. 'web_search', 'code_interpreter').
     */
    abstract public function type(): string;

    /**
     * Tool-specific configuration. Defaults to the pass-through options bag;
     * concrete tools override to fold their typed attributes in first.
     *
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->options;
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
