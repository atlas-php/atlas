<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Middleware\Contracts\AgentMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\ImageMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\ProviderMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\StepMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\ToolMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\VoiceHttpMiddleware;
use Atlasphp\Atlas\Persistence\Middleware\PersistConversation;
use Atlasphp\Atlas\Persistence\Middleware\TrackExecution;
use Atlasphp\Atlas\Persistence\Middleware\TrackProviderCall;
use Atlasphp\Atlas\Persistence\Middleware\TrackStep;
use Atlasphp\Atlas\Persistence\Middleware\TrackToolCall;

afterEach(function () {
    config()->set('atlas.middleware', []);
    config()->set('atlas.persistence.enabled', false);
    AtlasConfig::refresh();
});

it('lists middleware grouped by layer', function () {
    $agentMw = new class implements AgentMiddleware
    {
        public function handle($ctx, Closure $next): mixed
        {
            return $next($ctx);
        }
    };

    $providerMw = new class implements ProviderMiddleware
    {
        public function handle($ctx, Closure $next): mixed
        {
            return $next($ctx);
        }
    };

    config()->set('atlas.middleware', [$agentMw, $providerMw]);
    AtlasConfig::refresh();

    $this->artisan('atlas:middleware')
        ->assertSuccessful();
});

it('shows modality info for provider sub-interfaces', function () {
    $imageMw = new class implements ImageMiddleware
    {
        public function handle($ctx, Closure $next): mixed
        {
            return $next($ctx);
        }
    };

    config()->set('atlas.middleware', [$imageMw]);
    AtlasConfig::refresh();

    $this->artisan('atlas:middleware')
        ->assertSuccessful();
});

it('runs with no middleware configured', function () {
    config()->set('atlas.middleware', []);
    AtlasConfig::refresh();

    $this->artisan('atlas:middleware')
        ->assertSuccessful();
});

it('displays layer names in output', function () {
    config()->set('atlas.middleware', []);
    AtlasConfig::refresh();

    $this->artisan('atlas:middleware')
        ->expectsOutputToContain('Agent')
        ->expectsOutputToContain('Step')
        ->expectsOutputToContain('Tool')
        ->expectsOutputToContain('Provider')
        ->expectsOutputToContain('Voice HTTP')
        ->assertSuccessful();
});

it('shows persistence middleware when enabled', function () {
    config()->set('atlas.persistence.enabled', true);
    config()->set('atlas.middleware', [
        PersistConversation::class,
        TrackExecution::class,
        TrackStep::class,
        TrackToolCall::class,
        TrackProviderCall::class,
    ]);
    AtlasConfig::refresh();

    $this->artisan('atlas:middleware')
        ->expectsOutputToContain('PersistConversation')
        ->expectsOutputToContain('TrackExecution')
        ->expectsOutputToContain('TrackStep')
        ->expectsOutputToContain('TrackToolCall')
        ->expectsOutputToContain('TrackProviderCall')
        ->assertSuccessful();
});

it('shows mixed middleware types across layers', function () {
    $agentMw = new class implements AgentMiddleware
    {
        public function handle($ctx, Closure $next): mixed
        {
            return $next($ctx);
        }
    };

    $stepMw = new class implements StepMiddleware
    {
        public function handle($ctx, Closure $next): mixed
        {
            return $next($ctx);
        }
    };

    $toolMw = new class implements ToolMiddleware
    {
        public function handle($ctx, Closure $next): mixed
        {
            return $next($ctx);
        }
    };

    $imageMw = new class implements ImageMiddleware
    {
        public function handle($ctx, Closure $next): mixed
        {
            return $next($ctx);
        }
    };

    $voiceHttpMw = new class implements VoiceHttpMiddleware
    {
        public function handle($request, Closure $next): mixed
        {
            return $next($request);
        }
    };

    config()->set('atlas.middleware', [$agentMw, $stepMw, $toolMw, $imageMw, $voiceHttpMw]);
    AtlasConfig::refresh();

    $this->artisan('atlas:middleware')
        ->expectsOutputToContain('Agent')
        ->expectsOutputToContain('Step')
        ->expectsOutputToContain('Tool')
        ->expectsOutputToContain('Provider')
        ->expectsOutputToContain('Voice HTTP')
        ->assertSuccessful();
});
