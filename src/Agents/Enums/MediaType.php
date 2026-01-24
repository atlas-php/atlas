<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Enums;

/**
 * Enum representing supported media types for multimodal content.
 *
 * Defines the types of media that can be attached to messages.
 */
enum MediaType: string
{
    case Image = 'image';
    case Document = 'document';
    case Audio = 'audio';
    case Video = 'video';
}
