<?php

declare(strict_types=1);

namespace App\Pipelines;

use Atlasphp\Atlas\Agents\Support\AgentContext;
use Atlasphp\Atlas\Contracts\PipelineContract;
use Closure;

/**
 * Pipeline handler that injects metadata into the agent context.
 *
 * Demonstrates how to add custom metadata before agent execution
 * that can be accessed in the response via $response->metadata().
 */
class InjectMetadataHandler implements PipelineContract
{
    /**
     * Handle the pipeline data.
     *
     * @param  array{agent: mixed, input: string, context: AgentContext}  $data
     */
    public function handle(mixed $data, Closure $next): mixed
    {
        /** @var AgentContext $context */
        $context = $data['context'];

        // Inject custom metadata into the context
        $data['context'] = $context->mergeMetadata([
            'injected_at' => now()->toIso8601String(),
            'request_id' => uniqid('req_', true),
            'pipeline_demo' => true,
            'custom_data' => [
                'user_tier' => 'premium',
                'rate_limit' => 100,
            ],
        ]);

        return $next($data);
    }
}
