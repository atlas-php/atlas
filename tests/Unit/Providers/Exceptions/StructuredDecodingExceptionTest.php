<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Exceptions\ProviderException;
use Atlasphp\Atlas\Providers\Exceptions\StructuredDecodingException;
use Prism\Prism\Exceptions\PrismStructuredDecodingException;

test('it extends ProviderException', function () {
    $exception = StructuredDecodingException::fromPrism(
        new PrismStructuredDecodingException('invalid json')
    );

    expect($exception)->toBeInstanceOf(ProviderException::class);
});

test('it creates from Prism exception', function () {
    $prismException = new PrismStructuredDecodingException('{"invalid": json}');
    $atlasException = StructuredDecodingException::fromPrism($prismException);

    expect($atlasException)->toBeInstanceOf(StructuredDecodingException::class);
    expect($atlasException->getMessage())->toContain('could not be decoded');
    expect($atlasException->getPrevious())->toBe($prismException);
});

test('it preserves exception code', function () {
    $prismException = new PrismStructuredDecodingException('bad data');
    $atlasException = StructuredDecodingException::fromPrism($prismException);

    expect($atlasException->getCode())->toBe($prismException->getCode());
});
