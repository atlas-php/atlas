<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Exceptions\ProviderException;
use Atlasphp\Atlas\Providers\Exceptions\ProviderOverloadedException;
use Prism\Prism\Exceptions\PrismProviderOverloadedException;

test('it extends ProviderException', function () {
    $exception = ProviderOverloadedException::fromPrism(
        new PrismProviderOverloadedException('openai')
    );

    expect($exception)->toBeInstanceOf(ProviderException::class);
});

test('it creates from Prism exception', function () {
    $prismException = new PrismProviderOverloadedException('anthropic');
    $atlasException = ProviderOverloadedException::fromPrism($prismException);

    expect($atlasException)->toBeInstanceOf(ProviderOverloadedException::class);
    expect($atlasException->getMessage())->toContain('anthropic');
    expect($atlasException->getPrevious())->toBe($prismException);
});

test('it preserves exception code', function () {
    $prismException = new PrismProviderOverloadedException('openai');
    $atlasException = ProviderOverloadedException::fromPrism($prismException);

    expect($atlasException->getCode())->toBe($prismException->getCode());
});
