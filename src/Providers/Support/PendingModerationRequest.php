<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Support;

use Atlasphp\Atlas\Providers\Services\ModerationService;

/**
 * Fluent builder for content moderation operations.
 *
 * Provides a fluent API for configuring content moderation with metadata
 * and retry support. Uses immutable cloning for method chaining.
 */
final class PendingModerationRequest
{
    use HasMetadataSupport;
    use HasProviderSupport;
    use HasRetrySupport;

    public function __construct(
        private readonly ModerationService $moderationService,
    ) {}

    /**
     * Moderate text input(s).
     *
     * @param  string|array<string>  $input  Single text or array of texts to moderate.
     * @param  array<string, mixed>  $options  Additional options.
     */
    public function moderate(string|array $input, array $options = []): ModerationResponse
    {
        return $this->moderationService->moderate($input, $this->buildOptions($options), $this->getRetryArray());
    }

    /**
     * Build the options array from fluent configuration.
     *
     * @param  array<string, mixed>  $additionalOptions
     * @return array<string, mixed>
     */
    private function buildOptions(array $additionalOptions = []): array
    {
        $options = $additionalOptions;

        $provider = $this->getProviderOverride();
        if ($provider !== null) {
            $options['provider'] = $provider;
        }

        $model = $this->getModelOverride();
        if ($model !== null) {
            $options['model'] = $model;
        }

        $providerOptions = $this->getProviderOptions();
        if ($providerOptions !== []) {
            $options['provider_options'] = $providerOptions;
        }

        $metadata = $this->getMetadata();
        if ($metadata !== []) {
            $options['metadata'] = $metadata;
        }

        return $options;
    }
}
