<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers;

/**
 * Configuration for a provider driver instance.
 */
class ProviderConfig
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public readonly string $apiKey,
        public readonly string $baseUrl,
        public readonly ?string $organization = null,
        public readonly int $timeout = 60,
        public readonly int $reasoningTimeout = 300,
        public readonly int $mediaTimeout = 120,
        public readonly array $extra = [],
    ) {}

    /**
     * Create a ProviderConfig from a configuration array.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $known = ['api_key', 'url', 'organization', 'timeout', 'reasoning_timeout', 'media_timeout'];

        return new self(
            apiKey: (string) ($config['api_key'] ?? ''),
            baseUrl: (string) ($config['url'] ?? ''),
            organization: isset($config['organization']) ? (string) $config['organization'] : null,
            timeout: (int) ($config['timeout'] ?? 60),
            reasoningTimeout: (int) ($config['reasoning_timeout'] ?? 300),
            mediaTimeout: (int) ($config['media_timeout'] ?? 120),
            extra: array_diff_key($config, array_flip($known)),
        );
    }
}
