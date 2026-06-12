<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers;

/**
 * Configuration for a provider driver instance.
 */
class ProviderConfig
{
    /**
     * @param  array<string, bool>  $capabilityOverrides
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public readonly string $apiKey,
        public readonly string $baseUrl,
        public readonly ?string $organization = null,
        public readonly int $timeout = 60,
        public readonly int $mediaTimeout = 120,
        public readonly array $capabilityOverrides = [],
        public readonly array $extra = [],
        public readonly string $provider = '',
    ) {}

    /**
     * Resolve the consumer-facing provider name for attribution.
     *
     * Prefers the registry-stamped config key (e.g. 'groq', 'ollama') so a
     * provider built on a shared driver reports under its real name, and falls
     * back to the given driver default when no key was stamped.
     */
    public function providerName(string $default): string
    {
        return $this->provider !== '' ? $this->provider : $default;
    }

    /**
     * Create a ProviderConfig from a configuration array.
     *
     * The provider key is injected by the registry under `provider` so handlers
     * can attribute transport events to the provider that sent them.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $known = ['api_key', 'url', 'base_url', 'organization', 'timeout', 'media_timeout', 'driver', 'capabilities', 'provider'];

        return new self(
            apiKey: (string) ($config['api_key'] ?? ''),
            baseUrl: (string) ($config['base_url'] ?? $config['url'] ?? ''),
            organization: isset($config['organization']) ? (string) $config['organization'] : null,
            // Falls back to the global default request timeout (atlas.retry.timeout)
            // when a provider doesn't set its own, so ATLAS_TIMEOUT applies everywhere.
            timeout: (int) ($config['timeout'] ?? config('atlas.retry.timeout', 60)),
            mediaTimeout: (int) ($config['media_timeout'] ?? 120),
            capabilityOverrides: (array) ($config['capabilities'] ?? []),
            extra: array_diff_key($config, array_flip($known)),
            provider: (string) ($config['provider'] ?? ''),
        );
    }
}
