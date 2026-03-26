<?php

declare(strict_types=1);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Support\VariableRegistry;
use Atlasphp\Atlas\Testing\TextResponseFake;

it('interpolates instructions with variables', function () {
    $fake = Atlas::fake([TextResponseFake::make()]);

    Atlas::text('openai', 'gpt-4o')
        ->instructions('You work for {COMPANY}.')
        ->withVariables(['COMPANY' => 'Acme'])
        ->message('hello')
        ->asText();

    $fake->assertSentWith(fn ($r) => $r->request->instructions === 'You work for Acme.');
});

it('does not interpolate message content by default', function () {
    $fake = Atlas::fake([TextResponseFake::make()]);

    Atlas::text('openai', 'gpt-4o')
        ->instructions('Be helpful.')
        ->withVariables(['NAME' => 'Tim'])
        ->message('Hello {NAME}')
        ->asText();

    $fake->assertSentWith(fn ($r) => $r->request->message === 'Hello {NAME}');
});

it('interpolates message content with withMessageInterpolation', function () {
    $fake = Atlas::fake([TextResponseFake::make()]);

    Atlas::text('openai', 'gpt-4o')
        ->instructions('Be helpful.')
        ->withVariables(['NAME' => 'Tim'])
        ->message('Hello {NAME}')
        ->withMessageInterpolation()
        ->asText();

    $fake->assertSentWith(fn ($r) => $r->request->message === 'Hello Tim');
});

it('withVariables overrides config and registry', function () {
    config(['atlas.variables' => ['NAME' => 'Config']]);

    $registry = app(VariableRegistry::class);
    $registry->register('NAME', 'Registry');

    $fake = Atlas::fake([TextResponseFake::make()]);

    Atlas::text('openai', 'gpt-4o')
        ->instructions('Hi {NAME}')
        ->withVariables(['NAME' => 'Runtime'])
        ->message('hello')
        ->asText();

    $fake->assertSentWith(fn ($r) => $r->request->instructions === 'Hi Runtime');
});

it('meta flows to closure variables', function () {
    $registry = app(VariableRegistry::class);
    $registry->register('USER_NAME', fn (array $meta) => $meta['user_name'] ?? 'Guest');

    $fake = Atlas::fake([TextResponseFake::make()]);

    Atlas::text('openai', 'gpt-4o')
        ->instructions('Hello {USER_NAME}')
        ->withMeta(['user_name' => 'Tim'])
        ->message('hello')
        ->asText();

    $fake->assertSentWith(fn ($r) => $r->request->instructions === 'Hello Tim');
});
