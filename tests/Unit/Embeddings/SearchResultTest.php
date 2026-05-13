<?php

declare(strict_types=1);

use Atlasphp\Atlas\Embeddings\SearchResult;
use Atlasphp\Atlas\Tools\ToolSerializer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * In-memory model with a long body — proves the JSON trim drops it.
 */
class FakeBigBodyModel extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}

it('serializes to a trimmed shape via JsonSerializable', function () {
    $body = str_repeat('Lorem ipsum dolor sit amet. ', 500); // ~14KB
    $record = new FakeBigBodyModel(['id' => 42, 'title' => 'Big', 'body' => $body]);
    $record->id = 42;

    $result = new SearchResult(
        record: $record,
        content: 'the relevant chunk',
        similarity: 0.87,
        headingPath: 'A > B',
        ord: 3,
    );

    $decoded = json_decode(json_encode($result, JSON_THROW_ON_ERROR), true);

    expect(array_keys($decoded))->toEqualCanonicalizing([
        'id', 'type', 'content', 'similarity', 'heading_path', 'ord',
    ]);
    expect($decoded['id'])->toBe(42);
    expect($decoded['type'])->toBe(FakeBigBodyModel::class);
    expect($decoded['content'])->toBe('the relevant chunk');
    expect($decoded['similarity'])->toBe(0.87);
    expect($decoded['heading_path'])->toBe('A > B');
    expect($decoded['ord'])->toBe(3);
    expect(json_encode($result))->not->toContain('Lorem ipsum');
});

it('still exposes the full record via PHP property access', function () {
    $body = str_repeat('full record body. ', 200);
    $record = new FakeBigBodyModel(['title' => 'Big', 'body' => $body]);

    $result = new SearchResult(
        record: $record,
        content: 'chunk',
        similarity: 0.5,
    );

    // jsonSerialize trims, but the value object still holds the full model
    // for PHP consumers (rendering UI, follow-up loads, etc.).
    expect($result->record)->toBeInstanceOf(Model::class);
    expect($result->record->body)->toBe($body);
});

it('serializes a Collection of SearchResult through ToolSerializer to a tight JSON payload', function () {
    // Each record carries a long body. Old behavior (no JsonSerializable)
    // would produce 50KB+ JSON; the trim caps it at well under 2KB total.
    $body = str_repeat('massive body content. ', 500); // ~11KB per record

    $results = new Collection(array_map(function (int $i) use ($body) {
        $record = new FakeBigBodyModel(['title' => "Doc {$i}", 'body' => $body]);
        $record->id = $i;

        return new SearchResult(
            record: $record,
            content: "chunk {$i}",
            similarity: 0.9 - ($i * 0.1),
            headingPath: "Section {$i}",
            ord: $i,
        );
    }, [1, 2, 3]));

    $serialized = ToolSerializer::serialize($results);

    expect($serialized)->not->toContain('massive body content');
    expect(strlen($serialized))->toBeLessThan(2048);

    $decoded = json_decode($serialized, true);
    expect($decoded)->toHaveCount(3);
    foreach ($decoded as $i => $row) {
        expect(array_keys($row))->toEqualCanonicalizing([
            'id', 'type', 'content', 'similarity', 'heading_path', 'ord',
        ]);
        expect($row['id'])->toBe($i + 1);
        expect($row['content'])->toBe('chunk '.($i + 1));
    }
});

it('handles null heading_path and ord (whole-record mode)', function () {
    $record = new FakeBigBodyModel(['title' => 'X']);
    $record->id = 7;

    $result = new SearchResult(
        record: $record,
        content: 'the embedded text',
        similarity: 0.5,
    );

    $decoded = json_decode(json_encode($result, JSON_THROW_ON_ERROR), true);

    expect($decoded['heading_path'])->toBeNull();
    expect($decoded['ord'])->toBeNull();
});
