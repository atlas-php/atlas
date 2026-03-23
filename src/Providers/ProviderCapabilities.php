<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers;

/**
 * Declares what features a provider supports.
 */
class ProviderCapabilities
{
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
        public readonly bool $toolCalling = false,
        public readonly bool $providerTools = false,
        public readonly bool $models = false,
        public readonly bool $voice = false,
        public readonly bool $voices = false,
    ) {}

    /**
     * Check if a given feature is supported.
     */
    public function supports(string $feature): bool
    {
        return property_exists($this, $feature) && $this->{$feature};
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

        foreach ((new \ReflectionClass(self::class))->getConstructor()->getParameters() as $param) {
            $name = $param->getName();
            $args[$name] = $overrides[$name] ?? $base->{$name};
        }

        return new self(...$args);
    }
}
