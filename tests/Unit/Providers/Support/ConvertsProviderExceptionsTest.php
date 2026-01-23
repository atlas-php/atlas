<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Exceptions\ProviderOverloadedException;
use Atlasphp\Atlas\Providers\Exceptions\RateLimitedException;
use Atlasphp\Atlas\Providers\Exceptions\RequestTooLargeException;
use Atlasphp\Atlas\Providers\Exceptions\StructuredDecodingException;
use Atlasphp\Atlas\Providers\Support\ConvertsProviderExceptions;
use Prism\Prism\Exceptions\PrismProviderOverloadedException;
use Prism\Prism\Exceptions\PrismRateLimitedException;
use Prism\Prism\Exceptions\PrismRequestTooLargeException;
use Prism\Prism\Exceptions\PrismStructuredDecodingException;

class TestClassWithConverter
{
    use ConvertsProviderExceptions;

    public function convert(Throwable $e): Throwable
    {
        return $this->convertPrismException($e);
    }
}

beforeEach(function () {
    $this->converter = new TestClassWithConverter;
});

test('it converts PrismRateLimitedException', function () {
    $prism = new PrismRateLimitedException([], 60);

    $result = $this->converter->convert($prism);

    expect($result)->toBeInstanceOf(RateLimitedException::class);
    expect($result->getPrevious())->toBe($prism);
});

test('it converts PrismProviderOverloadedException', function () {
    $prism = new PrismProviderOverloadedException('openai');

    $result = $this->converter->convert($prism);

    expect($result)->toBeInstanceOf(ProviderOverloadedException::class);
    expect($result->getPrevious())->toBe($prism);
});

test('it converts PrismRequestTooLargeException', function () {
    $prism = new PrismRequestTooLargeException('anthropic');

    $result = $this->converter->convert($prism);

    expect($result)->toBeInstanceOf(RequestTooLargeException::class);
    expect($result->getPrevious())->toBe($prism);
});

test('it converts PrismStructuredDecodingException', function () {
    $prism = new PrismStructuredDecodingException('invalid json');

    $result = $this->converter->convert($prism);

    expect($result)->toBeInstanceOf(StructuredDecodingException::class);
    expect($result->getPrevious())->toBe($prism);
});

test('it returns unknown exceptions unchanged', function () {
    $generic = new RuntimeException('Generic error');

    $result = $this->converter->convert($generic);

    expect($result)->toBe($generic);
});

test('it returns other Prism exceptions unchanged', function () {
    $prism = new \Prism\Prism\Exceptions\PrismException('Generic Prism error');

    $result = $this->converter->convert($prism);

    expect($result)->toBe($prism);
});
