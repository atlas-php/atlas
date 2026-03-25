<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Enums;

use Atlasphp\Atlas\Persistence\Enums\ExecutionType;

/**
 * Identifies the type of operation for modality lifecycle events.
 *
 * @see ExecutionType — persistence-layer counterpart with
 *      near-identical cases. Modality includes SpeechToText (fired as a sub-operation event);
 *      ExecutionType omits it (a single execution tracks the parent Speech request).
 *      When adding a new modality, update both enums.
 */
enum Modality: string
{
    case Text = 'text';
    case Stream = 'stream';
    case Structured = 'structured';
    case Image = 'image';
    case ImageToText = 'image_to_text';
    case Audio = 'audio';
    case AudioToText = 'audio_to_text';
    case Video = 'video';
    case VideoToText = 'video_to_text';
    case Music = 'music';
    case Sfx = 'sfx';
    case Speech = 'speech';
    case SpeechToText = 'speech_to_text';
    case Embed = 'embed';
    case Moderate = 'moderate';
    case Voice = 'voice';
    case Rerank = 'rerank';
}
