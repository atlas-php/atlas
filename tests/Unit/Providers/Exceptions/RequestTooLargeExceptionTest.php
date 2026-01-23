<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Exceptions\ProviderException;
use Atlasphp\Atlas\Providers\Exceptions\RequestTooLargeException;
use Prism\Prism\Exceptions\PrismRequestTooLargeException;

test('it extends ProviderException', function () {
    $exception = RequestTooLargeException::fromPrism(
        new PrismRequestTooLargeException('openai')
    );

    expect($exception)->toBeInstanceOf(ProviderException::class);
});

test('it creates from Prism exception', function () {
    $prismException = new PrismRequestTooLargeException('anthropic');
    $atlasException = RequestTooLargeException::fromPrism($prismException);

    expect($atlasException)->toBeInstanceOf(RequestTooLargeException::class);
    expect($atlasException->getMessage())->toContain('anthropic');
    expect($atlasException->getPrevious())->toBe($prismException);
});

test('it preserves exception code', function () {
    $prismException = new PrismRequestTooLargeException('openai');
    $atlasException = RequestTooLargeException::fromPrism($prismException);

    expect($atlasException->getCode())->toBe($prismException->getCode());
});
