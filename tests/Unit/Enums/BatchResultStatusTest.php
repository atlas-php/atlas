<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\BatchResultStatus;

it('reports only succeeded as successful', function () {
    expect(BatchResultStatus::Succeeded->isSuccessful())->toBeTrue();

    foreach ([BatchResultStatus::Errored, BatchResultStatus::Expired, BatchResultStatus::Cancelled] as $status) {
        expect($status->isSuccessful())->toBeFalse();
    }
});

it('exposes stable string values', function () {
    expect(BatchResultStatus::Succeeded->value)->toBe('succeeded');
    expect(BatchResultStatus::Errored->value)->toBe('errored');
    expect(BatchResultStatus::from('expired'))->toBe(BatchResultStatus::Expired);
});
