<?php

declare(strict_types=1);

use Atlasphp\Atlas\Responses\RerankResult;

it('constructs with index, score, and document', function () {
    $result = new RerankResult(2, 0.95, 'Hello world');

    expect($result->index)->toBe(2);
    expect($result->score)->toBe(0.95);
    expect($result->document)->toBe('Hello world');
});
