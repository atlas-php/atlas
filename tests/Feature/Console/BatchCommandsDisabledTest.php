<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasConfig;

// These run under the default TestCase, where persistence is disabled — so the
// batch commands must no-op gracefully rather than touch a non-existent table.

beforeEach(function () {
    expect(app(AtlasConfig::class)->persistenceEnabled)->toBeFalse();
});

it('batch-poll is a no-op when persistence is disabled', function () {
    $this->artisan('atlas:batch-poll')
        ->expectsOutputToContain('Persistence is not enabled')
        ->assertSuccessful();
});

it('batch-prune is a no-op when persistence is disabled', function () {
    $this->artisan('atlas:batch-prune')
        ->expectsOutputToContain('Persistence is not enabled')
        ->assertSuccessful();
});
