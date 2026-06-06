<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Tools;

/**
 * Web search provider tool configuration.
 *
 * Options are provider-neutral; each provider's ToolMapper translates them to
 * that provider's native request shape (OpenAI/xAI nest domain filters under
 * `filters`; Anthropic emits a versioned `web_search_*` type with top-level
 * `allowed_domains`). Domain scoping lets an agent restrict ("include") or
 * exclude specific sites.
 *
 * `allowedDomains` / `blockedDomains` are the modelled, verified attributes;
 * pass anything else a provider supports (e.g. `search_context_size`,
 * `user_location`, `max_uses`) through the `$options` bag and it is merged in.
 */
class WebSearch extends ProviderTool
{
    /**
     * @param  array<int, string>|null  $allowedDomains  Restrict results to these domains (site inclusion)
     * @param  array<int, string>|null  $blockedDomains  Exclude results from these domains
     * @param  array<string, mixed>  $options  Extra provider-native attributes merged verbatim
     * @param  int|null  $maxResults  @deprecated No web_search API accepts this; kept for BC, never emitted.
     * @param  string|null  $locale  @deprecated No web_search API accepts this; kept for BC, never emitted.
     */
    public function __construct(
        protected readonly ?array $allowedDomains = null,
        protected readonly ?array $blockedDomains = null,
        array $options = [],
        protected readonly ?int $maxResults = null,
        protected readonly ?string $locale = null,
    ) {
        parent::__construct($options);
    }

    public function type(): string
    {
        return 'web_search';
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return array_merge(
            array_filter([
                'allowed_domains' => $this->allowedDomains ?: null,
                'blocked_domains' => $this->blockedDomains ?: null,
            ]),
            $this->options,
        );
    }
}
