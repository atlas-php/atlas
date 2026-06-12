<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers;

use Atlasphp\Atlas\Enums\Modality;

/**
 * Declares what features a provider supports.
 */
class ProviderCapabilities
{
    /**
     * @param  array<int, string>  $batchModalities  Modality values (see {@see Modality})
     *                                               this provider can submit as deferred batch jobs.
     */
    public function __construct(
        public readonly bool $text = false,
        public readonly bool $stream = false,
        public readonly bool $structured = false,
        public readonly bool $image = false,
        public readonly bool $imageToText = false,
        public readonly bool $audio = false,
        public readonly bool $audioToText = false,
        public readonly bool $video = false,
        public readonly bool $videoToText = false,
        public readonly bool $embed = false,
        public readonly bool $moderate = false,
        public readonly bool $rerank = false,
        public readonly bool $vision = false,
        public readonly bool $caching = false,
        public readonly bool $toolCalling = false,
        public readonly bool $providerTools = false,
        public readonly bool $models = false,
        public readonly bool $voice = false,
        public readonly bool $voices = false,
        public readonly bool $batch = false,
        public readonly array $batchModalities = [],
    ) {}

    /**
     * Check if a given feature is supported.
     */
    public function supports(string $feature): bool
    {
        return property_exists($this, $feature) && $this->{$feature} === true;
    }

    /**
     * Whether this provider can batch the given modality.
     *
     * Requires both the batch capability and the modality being in the
     * provider's batchable allow-list.
     */
    public function canBatch(Modality|string $modality): bool
    {
        $value = $modality instanceof Modality ? $modality->value : $modality;

        return $this->batch && in_array($value, $this->batchModalities, true);
    }

    /**
     * Create a new instance with config-level overrides applied.
     *
     * @param  array<string, bool>  $overrides
     */
    public static function withOverrides(self $base, array $overrides): self
    {
        if ($overrides === []) {
            return $base;
        }

        $args = [];

        foreach (get_object_vars($base) as $name => $value) {
            $args[$name] = $overrides[$name] ?? $value;
        }

        return new self(...$args);
    }
}
