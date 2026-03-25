<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Tools;

/**
 * xAI X/Twitter search provider tool configuration.
 *
 * Enables searching X (formerly Twitter) posts with optional date
 * filtering, handle restrictions, and media understanding.
 */
class XSearch extends ProviderTool
{
    /**
     * @param  string|null  $fromDate  ISO-8601 date string (e.g. "2025-01-01")
     * @param  string|null  $toDate  ISO-8601 date string (e.g. "2025-12-31")
     * @param  array<int, string>|null  $allowedXHandles
     */
    public function __construct(
        protected readonly ?string $fromDate = null,
        protected readonly ?string $toDate = null,
        protected readonly ?array $allowedXHandles = null,
        protected readonly bool $enableImageUnderstanding = false,
        protected readonly bool $enableVideoUnderstanding = false,
    ) {}

    public function type(): string
    {
        return 'x_search';
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        $config = [];

        if ($this->fromDate !== null) {
            $config['from_date'] = $this->fromDate;
        }
        if ($this->toDate !== null) {
            $config['to_date'] = $this->toDate;
        }
        if ($this->allowedXHandles !== null) {
            $config['allowed_x_handles'] = $this->allowedXHandles;
        }
        if ($this->enableImageUnderstanding) {
            $config['enable_image_understanding'] = true;
        }
        if ($this->enableVideoUnderstanding) {
            $config['enable_video_understanding'] = true;
        }

        return $config;
    }
}
