<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\BatchStatus;

it('marks completed/failed/expired/cancelled as terminal', function (BatchStatus $status) {
    expect($status->isTerminal())->toBeTrue();
})->with([
    BatchStatus::Completed,
    BatchStatus::Failed,
    BatchStatus::Expired,
    BatchStatus::Cancelled,
]);

it('marks in-flight states as non-terminal', function (BatchStatus $status) {
    expect($status->isTerminal())->toBeFalse();
})->with([
    BatchStatus::Validating,
    BatchStatus::InProgress,
    BatchStatus::Finalizing,
    BatchStatus::Cancelling,
]);

it('reports only completed as successful', function () {
    expect(BatchStatus::Completed->isSuccessful())->toBeTrue();

    foreach ([BatchStatus::Validating, BatchStatus::InProgress, BatchStatus::Finalizing, BatchStatus::Failed, BatchStatus::Expired, BatchStatus::Cancelling, BatchStatus::Cancelled] as $status) {
        expect($status->isSuccessful())->toBeFalse();
    }
});

it('exposes stable string values', function () {
    expect(BatchStatus::InProgress->value)->toBe('in_progress');
    expect(BatchStatus::from('completed'))->toBe(BatchStatus::Completed);
});
