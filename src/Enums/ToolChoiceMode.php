<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Enums;

/**
 * How a model should decide whether to call tools on a turn.
 *
 * Provider-normalized: each provider's ToolMapper translates these to the
 * vendor's own shape (OpenAI/xAI string, Anthropic object, Google tool_config).
 */
enum ToolChoiceMode: string
{
    /** The model decides whether to call a tool (provider default when tools exist). */
    case Auto = 'auto';

    /** The model must call a tool — any of the available tools, or a specific one. */
    case Required = 'required';

    /** The model must not call any tool. */
    case None = 'none';
}
