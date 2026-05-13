<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Schema\ChunkedEmbeddingColumns;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('fake_chunkable_table');
    Schema::create('fake_chunkable_table', function (Blueprint $table) {
        $table->id();
        $table->timestamps();
        ChunkedEmbeddingColumns::add($table);
    });
});

afterEach(function () {
    Schema::dropIfExists('fake_chunkable_table');
});

it('add installs every chunked-embedding tracking column', function () {
    expect(Schema::hasColumn('fake_chunkable_table', 'content_hash'))->toBeTrue();
    expect(Schema::hasColumn('fake_chunkable_table', 'indexed_hash'))->toBeTrue();
    expect(Schema::hasColumn('fake_chunkable_table', 'indexed_at'))->toBeTrue();
    expect(Schema::hasColumn('fake_chunkable_table', 'last_index_error'))->toBeTrue();
    expect(Schema::hasColumn('fake_chunkable_table', 'index_failure_count'))->toBeTrue();
});

it('drop removes every chunked-embedding tracking column added by add', function () {
    Schema::table('fake_chunkable_table', function (Blueprint $table) {
        ChunkedEmbeddingColumns::drop($table);
    });

    expect(Schema::hasColumn('fake_chunkable_table', 'content_hash'))->toBeFalse();
    expect(Schema::hasColumn('fake_chunkable_table', 'indexed_hash'))->toBeFalse();
    expect(Schema::hasColumn('fake_chunkable_table', 'indexed_at'))->toBeFalse();
    expect(Schema::hasColumn('fake_chunkable_table', 'last_index_error'))->toBeFalse();
    expect(Schema::hasColumn('fake_chunkable_table', 'index_failure_count'))->toBeFalse();
    // The id and timestamps columns from the original schema remain untouched.
    expect(Schema::hasColumn('fake_chunkable_table', 'id'))->toBeTrue();
    expect(Schema::hasColumn('fake_chunkable_table', 'created_at'))->toBeTrue();
});
