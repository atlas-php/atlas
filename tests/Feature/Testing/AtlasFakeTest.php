<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Responses\AudioResponse;
use Atlasphp\Atlas\Responses\EmbeddingsResponse;
use Atlasphp\Atlas\Responses\ImageResponse;
use Atlasphp\Atlas\Responses\ModerationResponse;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\VideoResponse;
use Atlasphp\Atlas\Testing\AtlasFake;
use Atlasphp\Atlas\Testing\AudioResponseFake;
use Atlasphp\Atlas\Testing\EmbeddingsResponseFake;
use Atlasphp\Atlas\Testing\ImageResponseFake;
use Atlasphp\Atlas\Testing\ModerationResponseFake;
use Atlasphp\Atlas\Testing\StreamResponseFake;
use Atlasphp\Atlas\Testing\TextResponseFake;
use Atlasphp\Atlas\Testing\VideoResponseFake;

it('swaps the manager with AtlasFake', function () {
    Atlas::fake();

    expect(Atlas::getFacadeRoot())->toBeInstanceOf(AtlasFake::class);
});

it('handles text entry point', function () {
    $fake = Atlas::fake([
        TextResponseFake::make()->withText('hi'),
    ]);

    $response = Atlas::text('openai', 'gpt-4o')->message('hello')->asText();

    expect($response)->toBeInstanceOf(TextResponse::class);
    expect($response->text)->toBe('hi');
});

it('handles stream entry point', function () {
    $fake = Atlas::fake([
        StreamResponseFake::make()->withText('stream me'),
    ]);

    $response = Atlas::text('openai', 'gpt-4o')->message('hello')->asStream();

    expect($response)->toBeInstanceOf(StreamResponse::class);

    $chunks = iterator_to_array($response);
    $doneChunks = array_filter($chunks, fn ($c) => $c->type === ChunkType::Done);

    expect($doneChunks)->toHaveCount(1);
    expect($response->getText())->toBe('stream me');
});

it('handles image entry point', function () {
    $fake = Atlas::fake([
        ImageResponseFake::make()->withUrl('https://test.com/image.png'),
    ]);

    $response = Atlas::image('openai', 'dall-e-3')->instructions('A cat')->asImage();

    expect($response)->toBeInstanceOf(ImageResponse::class);
    expect($response->url)->toBe('https://test.com/image.png');
});

it('handles audio entry point', function () {
    $fake = Atlas::fake([
        AudioResponseFake::make()->withData('audio-data'),
    ]);

    $response = Atlas::audio('openai', 'tts-1')->instructions('Say hello')->asAudio();

    expect($response)->toBeInstanceOf(AudioResponse::class);
    expect($response->data)->toBe('audio-data');
});

it('handles video entry point', function () {
    $fake = Atlas::fake([
        VideoResponseFake::make()->withUrl('https://test.com/video.mp4'),
    ]);

    $response = Atlas::video('openai', 'sora')->instructions('A sunset')->asVideo();

    expect($response)->toBeInstanceOf(VideoResponse::class);
    expect($response->url)->toBe('https://test.com/video.mp4');
});

it('handles embed entry point', function () {
    $fake = Atlas::fake([
        EmbeddingsResponseFake::make()->withEmbeddings([[0.5, 0.6]]),
    ]);

    $response = Atlas::embed('openai', 'text-embedding-3-small')->fromInput('test')->asEmbeddings();

    expect($response)->toBeInstanceOf(EmbeddingsResponse::class);
    expect($response->embeddings)->toBe([[0.5, 0.6]]);
});

it('handles moderate entry point', function () {
    $fake = Atlas::fake([
        ModerationResponseFake::make()->withFlagged(true),
    ]);

    $response = Atlas::moderate('openai', 'text-moderation-latest')->fromInput('bad content')->asModeration();

    expect($response)->toBeInstanceOf(ModerationResponse::class);
    expect($response->flagged)->toBeTrue();
});

it('works with Provider enum', function () {
    $fake = Atlas::fake([
        TextResponseFake::make()->withText('enum works'),
    ]);

    $response = Atlas::text(Provider::OpenAI, 'gpt-4o')->message('hello')->asText();

    expect($response->text)->toBe('enum works');
});

it('consumes responses in sequence order', function () {
    $fake = Atlas::fake([
        TextResponseFake::make()->withText('first'),
        TextResponseFake::make()->withText('second'),
    ]);

    $first = Atlas::text('openai', 'gpt-4o')->message('one')->asText();
    $second = Atlas::text('openai', 'gpt-4o')->message('two')->asText();

    expect($first->text)->toBe('first');
    expect($second->text)->toBe('second');
});

it('repeats last response when sequence is exhausted', function () {
    $fake = Atlas::fake([
        TextResponseFake::make()->withText('first'),
        TextResponseFake::make()->withText('last'),
    ]);

    Atlas::text('openai', 'gpt-4o')->message('one')->asText();
    Atlas::text('openai', 'gpt-4o')->message('two')->asText();
    $third = Atlas::text('openai', 'gpt-4o')->message('three')->asText();

    expect($third->text)->toBe('last');
});
