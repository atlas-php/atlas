<?php

declare(strict_types=1);

use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Testing\AudioResponseFake;

it('interpolates instructions for audio requests', function () {
    $fake = Atlas::fake([AudioResponseFake::make()]);

    Atlas::audio('openai', 'tts-1')
        ->instructions('Welcome to {COMPANY}')
        ->withVariables(['COMPANY' => 'Acme'])
        ->asAudio();

    $fake->assertSentWith(fn ($r) => $r->request->instructions === 'Welcome to Acme');
});

it('TTS instructions with dot notation variables', function () {
    $fake = Atlas::fake([AudioResponseFake::make()]);

    Atlas::audio('openai', 'tts-1')
        ->instructions('Your balance at {COMPANY.NAME} is {BALANCE}')
        ->withVariables([
            'COMPANY' => ['NAME' => 'Acme Bank'],
            'BALANCE' => '$142.50',
        ])
        ->asAudio();

    $fake->assertSentWith(fn ($r) => $r->request->instructions === 'Your balance at Acme Bank is $142.50');
});
