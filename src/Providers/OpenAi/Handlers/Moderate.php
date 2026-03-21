<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\OpenAi\Handlers;

use Atlasphp\Atlas\Providers\Concerns\BuildsHeaders;
use Atlasphp\Atlas\Providers\Handlers\ModerateHandler;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\OpenAi\HasOrganizationHeader;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\ModerateRequest;
use Atlasphp\Atlas\Responses\ModerationResponse;

/**
 * OpenAI moderation handler using the /v1/moderations endpoint.
 */
class Moderate implements ModerateHandler
{
    use BuildsHeaders, HasOrganizationHeader {
        HasOrganizationHeader::extraHeaders insteadof BuildsHeaders;
    }

    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
    ) {}

    public function moderate(ModerateRequest $request): ModerationResponse
    {
        $body = array_filter([
            'model' => $request->model,
            'input' => $request->input,
        ], fn (mixed $v): bool => $v !== null);

        $body = array_merge($body, $request->providerOptions);

        $data = $this->http->post(
            url: "{$this->config->baseUrl}/moderations",
            headers: $this->headers(),
            body: $body,
            timeout: $this->config->timeout,
        );

        /** @var array<string, mixed> $result */
        $result = $data['results'][0] ?? [];

        return new ModerationResponse(
            flagged: (bool) ($result['flagged'] ?? false),
            categories: $result['categories'] ?? [],
            meta: ['category_scores' => $result['category_scores'] ?? []],
        );
    }
}
