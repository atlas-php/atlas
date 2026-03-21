<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasManager;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Pending\AgentRequest;
use Atlasphp\Atlas\Pending\AudioRequest;
use Atlasphp\Atlas\Pending\EmbedRequest;
use Atlasphp\Atlas\Pending\ImageRequest;
use Atlasphp\Atlas\Pending\ModerateRequest;
use Atlasphp\Atlas\Pending\ProviderRequest;
use Atlasphp\Atlas\Pending\RerankRequest;
use Atlasphp\Atlas\Pending\TextRequest;
use Atlasphp\Atlas\Pending\VideoRequest;
use Atlasphp\Atlas\Persistence\Memory\MemoryBuilder;

it('text returns TextRequest', function () {
    $manager = app(AtlasManager::class);

    expect($manager->text('openai', 'gpt-4o'))->toBeInstanceOf(TextRequest::class);
});

it('image returns ImageRequest', function () {
    $manager = app(AtlasManager::class);

    expect($manager->image('openai', 'dall-e-3'))->toBeInstanceOf(ImageRequest::class);
});

it('audio returns AudioRequest', function () {
    $manager = app(AtlasManager::class);

    expect($manager->audio('openai', 'tts-1'))->toBeInstanceOf(AudioRequest::class);
});

it('video returns VideoRequest', function () {
    $manager = app(AtlasManager::class);

    expect($manager->video('openai', 'sora'))->toBeInstanceOf(VideoRequest::class);
});

it('embed returns EmbedRequest', function () {
    $manager = app(AtlasManager::class);

    expect($manager->embed('openai', 'text-embedding-3-small'))->toBeInstanceOf(EmbedRequest::class);
});

it('moderate returns ModerateRequest', function () {
    $manager = app(AtlasManager::class);

    expect($manager->moderate('openai', 'text-moderation-latest'))->toBeInstanceOf(ModerateRequest::class);
});

it('rerank returns RerankRequest', function () {
    $manager = app(AtlasManager::class);

    expect($manager->rerank('cohere', 'rerank-v3.5'))->toBeInstanceOf(RerankRequest::class);
});

it('provider returns ProviderRequest', function () {
    $manager = app(AtlasManager::class);

    expect($manager->provider('openai'))->toBeInstanceOf(ProviderRequest::class);
});

it('agent returns AgentRequest', function () {
    $manager = app(AtlasManager::class);

    expect($manager->agent('support'))->toBeInstanceOf(AgentRequest::class);
});

it('memory returns MemoryBuilder', function () {
    $manager = app(AtlasManager::class);

    expect($manager->memory())->toBeInstanceOf(MemoryBuilder::class);
});

it('accepts Provider enum', function () {
    $manager = app(AtlasManager::class);

    expect($manager->text(Provider::OpenAI, 'gpt-4o'))->toBeInstanceOf(TextRequest::class);
    expect($manager->provider(Provider::Anthropic))->toBeInstanceOf(ProviderRequest::class);
});
