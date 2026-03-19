<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers;

/**
 * A list of voices available from a provider.
 */
class VoiceList
{
    /**
     * @param  array<int, string>  $voices
     */
    public function __construct(
        public readonly array $voices = [],
    ) {}
}
