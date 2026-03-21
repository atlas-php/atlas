<?php

declare(strict_types=1);

use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Support\VariableRegistry;
use Atlasphp\Atlas\Testing\TextResponseFake;

it('config variables are available in instructions', function () {
    config(['atlas.variables' => ['BRAND' => 'TestBrand']]);

    $fake = Atlas::fake([TextResponseFake::make()]);

    Atlas::text('openai', 'gpt-4o')
        ->instructions('Welcome to {BRAND}')
        ->message('hello')
        ->asText();

    $fake->assertSentWith(fn ($r) => $r->request->instructions === 'Welcome to TestBrand');
});

it('nested config arrays accessible via dot notation', function () {
    config(['atlas.variables' => [
        'COMPANY' => ['NAME' => 'Acme', 'SUPPORT_EMAIL' => 'help@acme.com'],
    ]]);

    $fake = Atlas::fake([TextResponseFake::make()]);

    Atlas::text('openai', 'gpt-4o')
        ->instructions('Contact {COMPANY.NAME} at {COMPANY.SUPPORT_EMAIL}')
        ->message('hello')
        ->asText();

    $fake->assertSentWith(fn ($r) => $r->request->instructions === 'Contact Acme at help@acme.com');
});

it('config overridden by registry', function () {
    config(['atlas.variables' => ['NAME' => 'Config']]);

    $registry = app(VariableRegistry::class);
    $registry->register('NAME', 'Registry');

    $fake = Atlas::fake([TextResponseFake::make()]);

    Atlas::text('openai', 'gpt-4o')
        ->instructions('Hi {NAME}')
        ->message('hello')
        ->asText();

    $fake->assertSentWith(fn ($r) => $r->request->instructions === 'Hi Registry');
});

it('config overridden by per-call variables', function () {
    config(['atlas.variables' => ['NAME' => 'Config']]);

    $fake = Atlas::fake([TextResponseFake::make()]);

    Atlas::text('openai', 'gpt-4o')
        ->instructions('Hi {NAME}')
        ->withVariables(['NAME' => 'PerCall'])
        ->message('hello')
        ->asText();

    $fake->assertSentWith(fn ($r) => $r->request->instructions === 'Hi PerCall');
});

it('empty config causes no errors', function () {
    config(['atlas.variables' => []]);

    $fake = Atlas::fake([TextResponseFake::make()]);

    Atlas::text('openai', 'gpt-4o')
        ->instructions('Hello {UNKNOWN}')
        ->message('hello')
        ->asText();

    $fake->assertSentWith(fn ($r) => $r->request->instructions === 'Hello {UNKNOWN}');
});
