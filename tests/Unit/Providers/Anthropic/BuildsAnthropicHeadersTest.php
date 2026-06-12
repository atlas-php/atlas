<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Anthropic\Concerns\BuildsAnthropicHeaders;
use Atlasphp\Atlas\Providers\ProviderConfig;

function anthropicHeaderUser(ProviderConfig $config): object
{
    return new class($config)
    {
        use BuildsAnthropicHeaders;

        public function __construct(public ProviderConfig $config) {}

        /** @return array<string, string> */
        public function exposeHeaders(): array
        {
            return $this->headers();
        }

        /** @return array<string, string> */
        public function exposeHeadersWithoutContentType(): array
        {
            return $this->headersWithoutContentType();
        }
    };
}

it('builds full headers with the configured version and content-type', function () {
    $user = anthropicHeaderUser(new ProviderConfig(apiKey: 'sk-test', baseUrl: 'https://api.anthropic.com/v1', extra: ['version' => '2099-01-01']));

    expect($user->exposeHeaders())->toBe([
        'x-api-key' => 'sk-test',
        'anthropic-version' => '2099-01-01',
        'Content-Type' => 'application/json',
    ]);
});

it('builds headers without content-type for GET/multipart', function () {
    $user = anthropicHeaderUser(new ProviderConfig(apiKey: 'sk-test', baseUrl: 'https://api.anthropic.com/v1', extra: ['version' => '2099-01-01']));

    expect($user->exposeHeadersWithoutContentType())->toBe([
        'x-api-key' => 'sk-test',
        'anthropic-version' => '2099-01-01',
    ]);
});

it('defaults the anthropic version when none is configured', function () {
    $user = anthropicHeaderUser(new ProviderConfig(apiKey: 'sk-test', baseUrl: 'https://api.anthropic.com/v1'));

    expect($user->exposeHeadersWithoutContentType()['anthropic-version'])->toBe('2023-06-01');
});
