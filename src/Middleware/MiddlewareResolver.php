<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Middleware;

use Atlasphp\Atlas\Middleware\Contracts\AgentMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\AudioMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\EmbedMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\ImageMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\ProviderMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\StepMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\TextMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\ToolMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\VideoMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\VoiceHttpMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\VoiceMiddleware;
use Closure;
use Illuminate\Contracts\Container\Container;

/**
 * Routes middleware to execution layers based on interface implementation.
 *
 * Resolves the flat middleware config array into per-layer stacks by inspecting
 * which marker interfaces each class implements. Supports modality-specific
 * filtering for provider middleware via sub-interfaces.
 *
 * Each middleware must implement exactly one scope interface. All middleware
 * uses a single handle() method.
 */
class MiddlewareResolver
{
    /**
     * Modality sub-interface to dispatch method mapping.
     *
     * @var array<class-string, array<int, string>>
     */
    private const MODALITY_MAP = [
        TextMiddleware::class => ['text', 'stream', 'structured'],
        ImageMiddleware::class => ['image', 'imageToText'],
        AudioMiddleware::class => ['audio', 'audioToText'],
        VideoMiddleware::class => ['video', 'videoToText'],
        VoiceMiddleware::class => ['voice'],
        EmbedMiddleware::class => ['embed', 'moderate', 'rerank'],
    ];

    /**
     * Scope interface to layer name mapping.
     *
     * @var array<class-string, string>
     */
    private const SCOPE_MAP = [
        AgentMiddleware::class => 'agent',
        StepMiddleware::class => 'step',
        ToolMiddleware::class => 'tool',
        ProviderMiddleware::class => 'provider',
    ];

    /** @var array<string, array<int, mixed>>|null */
    private ?array $resolved = null;

    /** @var array<int, mixed>|null */
    private ?array $voiceHttp = null;

    /**
     * Provider middleware grouped by the methods they apply to.
     * Key '' means all methods (direct ProviderMiddleware).
     *
     * @var array<string, array<int, mixed>>|null
     */
    private ?array $providerByMethod = null;

    public function __construct(
        private readonly Container $container,
        /** @var array<int, mixed> */
        private readonly array $middleware,
    ) {}

    /**
     * Get middleware for a non-provider execution layer.
     *
     * @return array<int, mixed>
     */
    public function forLayer(string $layer): array
    {
        $this->resolve();

        return $this->resolved[$layer] ?? [];
    }

    /**
     * Get provider middleware filtered by dispatch method.
     *
     * Returns middleware that either targets all provider calls (direct
     * ProviderMiddleware) or specifically targets the given method via
     * a modality sub-interface.
     *
     * @return array<int, mixed>
     */
    public function forProvider(string $method): array
    {
        $this->resolve();

        $result = $this->providerByMethod[''] ?? [];

        foreach (self::MODALITY_MAP as $interface => $methods) {
            if (in_array($method, $methods, true) && isset($this->providerByMethod[$interface])) {
                $result = array_merge($result, $this->providerByMethod[$interface]);
            }
        }

        return $result;
    }

    /**
     * Get voice HTTP middleware for webhook routes.
     *
     * @return array<int, mixed>
     */
    public function forVoiceHttp(): array
    {
        $this->resolve();

        return $this->voiceHttp ?? [];
    }

    /**
     * Get all resolved middleware grouped by layer for inspection.
     *
     * @return array<string, array<int, array{class: string, modalities: array<int, string>}>>
     */
    public function all(): array
    {
        $this->resolve();

        $result = [
            'agent' => [],
            'step' => [],
            'tool' => [],
            'provider' => [],
            'voice_http' => [],
        ];

        foreach ($this->middleware as $entry) {
            if ($entry instanceof Closure) {
                $result['provider'][] = ['class' => 'Closure', 'modalities' => ['*']];

                continue;
            }

            $class = is_string($entry) ? $entry : $entry::class;
            $instance = is_string($entry) ? $this->container->make($entry) : $entry;

            if ($instance instanceof VoiceHttpMiddleware) {
                $result['voice_http'][] = ['class' => $class, 'modalities' => []];
            }

            foreach (self::SCOPE_MAP as $interface => $layer) {
                if (! ($instance instanceof $interface)) {
                    continue;
                }

                if ($layer === 'provider') {
                    $modalities = $this->resolveModalities($instance);
                    $result['provider'][] = ['class' => $class, 'modalities' => $modalities];
                } else {
                    $result[$layer][] = ['class' => $class, 'modalities' => []];
                }
            }
        }

        return $result;
    }

    /**
     * Resolve the flat middleware list into per-layer stacks.
     */
    private function resolve(): void
    {
        if ($this->resolved !== null) {
            return;
        }

        $this->resolved = ['agent' => [], 'step' => [], 'tool' => []];
        $this->providerByMethod = [];
        $this->voiceHttp = [];

        foreach ($this->middleware as $entry) {
            if ($entry instanceof Closure) {
                $this->providerByMethod[''][] = $entry;

                continue;
            }

            $instance = is_string($entry) ? $this->container->make($entry) : $entry;
            $scope = $this->resolveScope($instance);

            if ($scope === 'provider') {
                $this->addProviderMiddleware($instance);
            } elseif ($scope !== null) {
                $this->resolved[$scope][] = $instance;
            }

            if ($instance instanceof VoiceHttpMiddleware) {
                $this->voiceHttp[] = $instance;
            }
        }
    }

    /**
     * Determine the single execution scope for a middleware instance.
     *
     * Each middleware implements exactly one scope interface. Provider
     * sub-interfaces (TextMiddleware, ImageMiddleware, etc.) resolve
     * to 'provider' since they extend ProviderMiddleware.
     */
    private function resolveScope(object $instance): ?string
    {
        foreach (self::SCOPE_MAP as $interface => $layer) {
            if ($instance instanceof $interface) {
                return $layer;
            }
        }

        return null;
    }

    /**
     * Route provider middleware into the correct modality buckets.
     */
    private function addProviderMiddleware(object $instance): void
    {
        $matchedInterfaces = [];

        foreach (self::MODALITY_MAP as $interface => $methods) {
            if ($instance instanceof $interface) {
                $matchedInterfaces[] = $interface;
            }
        }

        if ($matchedInterfaces === []) {
            // Direct ProviderMiddleware — runs on all methods
            $this->providerByMethod[''][] = $instance;

            return;
        }

        // Add to each matching modality bucket
        foreach ($matchedInterfaces as $interface) {
            $this->providerByMethod[$interface][] = $instance;
        }
    }

    /**
     * Resolve which modality methods a provider middleware targets.
     *
     * @return array<int, string>
     */
    private function resolveModalities(object $instance): array
    {
        $methods = [];

        foreach (self::MODALITY_MAP as $interface => $modalityMethods) {
            if ($instance instanceof $interface) {
                $methods = array_merge($methods, $modalityMethods);
            }
        }

        return $methods === [] ? ['*'] : $methods;
    }
}
