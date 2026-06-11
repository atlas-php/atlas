<?php

declare(strict_types=1);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Responses\TokenCount;

it('counts tokens through the text builder without running generation', function () {
    $fake = Atlas::fake();

    $count = Atlas::text('openai', 'gpt-4o')
        ->message('Hello world')
        ->countTokens();

    expect($count)->toBeInstanceOf(TokenCount::class)
        ->and($count->inputTokens)->toBeGreaterThan(0)
        ->and($count->model)->toBe('gpt-4o');

    // Exactly one driver interaction — the count — and no generation alongside it.
    $fake->assertMethodCalled('countTokens');
    $fake->assertSentCount(1);
});
