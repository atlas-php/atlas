<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\AtlasManager;
use Atlasphp\Atlas\Exceptions\AtlasException;

it('throws AtlasException when no provider configured for text', function () {
    config()->set('atlas.defaults.text', []);
    AtlasConfig::refresh();

    $manager = app(AtlasManager::class);
    $manager->text();
})->throws(AtlasException::class, 'No provider specified and no default configured for text');

it('throws AtlasException when no provider configured for image', function () {
    config()->set('atlas.defaults.image', []);
    AtlasConfig::refresh();

    $manager = app(AtlasManager::class);
    $manager->image();
})->throws(AtlasException::class, 'No provider specified and no default configured for image');

it('throws AtlasException when no provider configured for video', function () {
    config()->set('atlas.defaults.video', []);
    AtlasConfig::refresh();

    $manager = app(AtlasManager::class);
    $manager->video();
})->throws(AtlasException::class, 'No provider specified and no default configured for video');

it('throws AtlasException when no provider configured for embed', function () {
    config()->set('atlas.defaults.embed', []);
    AtlasConfig::refresh();

    $manager = app(AtlasManager::class);
    $manager->embed();
})->throws(AtlasException::class, 'No provider specified and no default configured for embed');

it('throws AtlasException when no provider configured for moderate', function () {
    config()->set('atlas.defaults.moderate', []);
    AtlasConfig::refresh();

    $manager = app(AtlasManager::class);
    $manager->moderate();
})->throws(AtlasException::class, 'No provider specified and no default configured for moderate');

it('throws AtlasException when no provider configured for rerank', function () {
    config()->set('atlas.defaults.rerank', []);
    AtlasConfig::refresh();

    $manager = app(AtlasManager::class);
    $manager->rerank();
})->throws(AtlasException::class, 'No provider specified and no default configured for rerank');

it('includes env var hint in exception message', function () {
    config()->set('atlas.defaults.text', []);
    AtlasConfig::refresh();

    try {
        app(AtlasManager::class)->text();
        $this->fail('Expected AtlasException');
    } catch (AtlasException $e) {
        expect($e->getMessage())->toContain('ATLAS_TEXT_PROVIDER');
    }
});
