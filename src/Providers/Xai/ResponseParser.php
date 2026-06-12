<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Xai;

use Atlasphp\Atlas\Providers\Responses\ResponseParser as ResponsesResponseParser;

/**
 * Parses xAI's Responses-compatible output.
 *
 * Identical to the Responses base except for reasoning extraction: xAI grok
 * models emit reasoning text under the `content` parts of a reasoning item
 * rather than OpenAI's `summary` field.
 */
class ResponseParser extends ResponsesResponseParser
{
    /**
     * Read the OpenAI `summary` shape first, then fall back to xAI's `content`
     * parts so grok reasoning text is still captured.
     *
     * @param  array<string, mixed>  $item
     */
    protected function extractReasoningText(array $item): ?string
    {
        $summary = parent::extractReasoningText($item);

        if ($summary !== null) {
            return $summary;
        }

        $text = '';

        /** @var array<int, array<string, mixed>> $content */
        $content = $item['content'] ?? [];

        foreach ($content as $part) {
            $partText = (string) ($part['text'] ?? '');

            if ($partText !== '') {
                $text .= $partText."\n";
            }
        }

        $text = rtrim($text, "\n");

        return $text !== '' ? $text : null;
    }
}
