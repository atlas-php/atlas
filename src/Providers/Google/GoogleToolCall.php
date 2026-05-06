<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Google;

use Atlasphp\Atlas\Messages\ToolCall;

/**
 * Represents a Gemini function call with Google-specific continuation metadata.
 */
class GoogleToolCall extends ToolCall
{
    public const THOUGHT_SIGNATURE_METADATA_KEY = 'google_thought_signature';

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        string $id,
        string $name,
        array $arguments,
        public readonly ?string $thoughtSignature = null,
    ) {
        parent::__construct($id, $name, $arguments);
    }
}
