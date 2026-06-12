<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Requests\Batch;
use Atlasphp\Atlas\Requests\BatchLine;
use Atlasphp\Atlas\Requests\EmbedRequest;
use Atlasphp\Atlas\Requests\TextRequest;

it('wraps a request DTO with a custom id', function () {
    $request = new TextRequest('gpt-5', null, 'Caption this.', [], [], null, null, null, [], [], []);
    $line = new BatchLine('img-1', $request);

    expect($line->customId)->toBe('img-1');
    expect($line->request)->toBe($request);
});

it('holds provider, modality, lines and a default completion window', function () {
    $lines = [
        new BatchLine('a', new EmbedRequest('text-embedding-3-small', 'one')),
        new BatchLine('b', new EmbedRequest('text-embedding-3-small', 'two')),
    ];

    $batch = new Batch('openai', Modality::Embed, $lines);

    expect($batch->provider)->toBe('openai');
    expect($batch->modality)->toBe(Modality::Embed);
    expect($batch->lines)->toHaveCount(2);
    expect($batch->completionWindow)->toBe('24h');
    expect($batch->count())->toBe(2);
});

it('accepts a custom completion window', function () {
    $batch = new Batch('anthropic', Modality::Text, [], '12h');

    expect($batch->completionWindow)->toBe('12h');
    expect($batch->count())->toBe(0);
});
