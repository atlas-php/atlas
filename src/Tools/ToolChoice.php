<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools;

use Atlasphp\Atlas\Enums\ToolChoiceMode;

/**
 * Provider-normalized tool-choice intent.
 *
 * Expresses, once, whether the model should call tools — let it decide (`auto`),
 * require any tool (`required`), require a specific named tool (`tool`), or forbid
 * tools (`none`). Each provider's ToolMapper translates this to the vendor's own
 * shape, so a consumer never hand-writes provider-specific `tool_choice` payloads.
 */
final class ToolChoice
{
    private function __construct(
        public readonly ToolChoiceMode $mode,
        public readonly ?string $tool = null,
    ) {}

    /** Let the model decide whether to call a tool. */
    public static function auto(): self
    {
        return new self(ToolChoiceMode::Auto);
    }

    /** Require the model to call one of the available tools. */
    public static function required(): self
    {
        return new self(ToolChoiceMode::Required);
    }

    /** Forbid the model from calling any tool. */
    public static function none(): self
    {
        return new self(ToolChoiceMode::None);
    }

    /** Require the model to call this specific tool by name. */
    public static function tool(string $name): self
    {
        return new self(ToolChoiceMode::Required, $name);
    }

    /** Whether a specific named tool was requested. */
    public function isSpecificTool(): bool
    {
        return $this->mode === ToolChoiceMode::Required && $this->tool !== null;
    }

    /**
     * Primitive representation for queue serialization (survives any queue driver).
     *
     * @return array{mode: string, tool: ?string}
     */
    public function toArray(): array
    {
        return ['mode' => $this->mode->value, 'tool' => $this->tool];
    }

    /**
     * Rebuild from {@see toArray()} output.
     *
     * @param  array{mode: string, tool?: ?string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(ToolChoiceMode::from($data['mode']), $data['tool'] ?? null);
    }
}
