<?php

declare(strict_types=1);

use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Testing\ImageResponseFake;

it('interpolates instructions for image requests', function () {
    $fake = Atlas::fake([ImageResponseFake::make()]);

    Atlas::image('openai', 'dall-e-3')
        ->instructions('Generate a logo for {COMPANY}')
        ->withVariables(['COMPANY' => 'Acme'])
        ->asImage();

    $fake->assertSentWith(fn ($r) => $r->request->instructions === 'Generate a logo for Acme');
});

it('withVariables works on ImageRequest', function () {
    config(['atlas.variables' => ['STYLE' => 'minimalist']]);

    $fake = Atlas::fake([ImageResponseFake::make()]);

    Atlas::image('openai', 'dall-e-3')
        ->instructions('A {STYLE} logo for {BRAND}')
        ->withVariables(['BRAND' => 'Atlas'])
        ->asImage();

    $fake->assertSentWith(fn ($r) => $r->request->instructions === 'A minimalist logo for Atlas');
});
