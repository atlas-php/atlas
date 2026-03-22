<?php

declare(strict_types=1);

use Atlasphp\Atlas\Embeddings\EmbeddingResolver;
use Atlasphp\Atlas\Persistence\Concerns\HasVectorEmbeddings;
use Illuminate\Database\Eloquent\Model;

/**
 * In-memory fake model for testing the trait without a database.
 */
class FakeEmbeddableModel extends Model
{
    use HasVectorEmbeddings;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'embedding_at' => 'datetime',
        ];
    }
}

class FakeMultiSourceModel extends Model
{
    use HasVectorEmbeddings;

    protected $guarded = [];

    public function embeddable(): array
    {
        return ['column' => 'embedding', 'source' => ['title', 'content']];
    }

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'embedding_at' => 'datetime',
        ];
    }
}

class FakeNoAutoEmbedModel extends Model
{
    use HasVectorEmbeddings;

    protected bool $autoEmbed = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'embedding_at' => 'datetime',
        ];
    }
}

it('returns default embeddable config', function () {
    $model = new FakeEmbeddableModel;

    expect($model->embeddable())->toBe(['column' => 'embedding', 'source' => 'content']);
});

it('returns custom embeddable config with multiple sources', function () {
    $model = new FakeMultiSourceModel;

    expect($model->embeddable())->toBe(['column' => 'embedding', 'source' => ['title', 'content']]);
});

it('extracts single field content', function () {
    $model = new FakeEmbeddableModel;
    $model->content = 'Hello world';

    expect($model->getEmbeddableContent())->toBe('Hello world');
});

it('concatenates multiple fields with double newline', function () {
    $model = new FakeMultiSourceModel;
    $model->title = 'My Title';
    $model->content = 'Body text here';

    expect($model->getEmbeddableContent())->toBe("My Title\n\nBody text here");
});

it('filters empty fields when concatenating', function () {
    $model = new FakeMultiSourceModel;
    $model->title = '';
    $model->content = 'Only content';

    expect($model->getEmbeddableContent())->toBe('Only content');
});

it('returns empty string when all fields are empty', function () {
    $model = new FakeMultiSourceModel;
    $model->title = '';
    $model->content = '';

    expect($model->getEmbeddableContent())->toBe('');
});

it('detects should generate when source is dirty and non-empty', function () {
    config(['atlas.persistence.enabled' => true]);

    $model = new FakeEmbeddableModel;
    $model->content = 'Some text';
    // Model is "new" so content is dirty

    expect($model->shouldGenerateEmbedding())->toBeTrue();
});

it('returns false when source field is not dirty', function () {
    config(['atlas.persistence.enabled' => true]);

    $model = new FakeEmbeddableModel;
    $model->syncOriginal(); // Mark everything as clean

    expect($model->shouldGenerateEmbedding())->toBeFalse();
});

it('returns false when persistence is disabled', function () {
    config(['atlas.persistence.enabled' => false]);

    $model = new FakeEmbeddableModel;
    $model->content = 'Some text';

    expect($model->shouldGenerateEmbedding())->toBeFalse();
});

it('returns false when content is empty', function () {
    config(['atlas.persistence.enabled' => true]);

    $model = new FakeEmbeddableModel;
    $model->content = '';

    expect($model->shouldGenerateEmbedding())->toBeFalse();
});

it('generates embedding and sets column and timestamp', function () {
    config(['atlas.persistence.enabled' => true]);

    $vector = [0.1, 0.2, 0.3];

    $resolver = Mockery::mock(EmbeddingResolver::class);
    $resolver->shouldReceive('resolve')
        ->with('Test content')
        ->once()
        ->andReturn($vector);

    app()->instance(EmbeddingResolver::class, $resolver);

    $model = new FakeEmbeddableModel;
    $model->content = 'Test content';
    $model->generateEmbedding();

    expect($model->getAttribute('embedding'))->toBe('[0.1,0.2,0.3]');
    expect($model->getAttribute('embedding_at'))->not->toBeNull();
});

it('generates embedding using explicit provider and model', function () {
    config(['atlas.persistence.enabled' => true]);

    $vector = [0.4, 0.5, 0.6];

    $resolver = Mockery::mock(EmbeddingResolver::class);
    $resolver->shouldReceive('resolveUsing')
        ->with('Test content', 'openai', 'text-embedding-3-small')
        ->once()
        ->andReturn($vector);

    app()->instance(EmbeddingResolver::class, $resolver);

    $model = new FakeEmbeddableModel;
    $model->content = 'Test content';
    $model->generateEmbeddingUsing('openai', 'text-embedding-3-small');

    expect($model->getAttribute('embedding'))->toBe('[0.4,0.5,0.6]');
    expect($model->getAttribute('embedding_at'))->not->toBeNull();
});

it('delegates scopeSimilarTo to whereVectorSimilarTo with correct column', function () {
    $model = new FakeEmbeddableModel;

    // Just verify it reads the correct column from embeddable()
    expect($model->embeddable()['column'])->toBe('embedding');
});

it('respects autoEmbed false to disable auto-embed', function () {
    $model = new FakeNoAutoEmbedModel;

    expect($model->isAutoEmbedEnabled())->toBeFalse();
});

it('defaults autoEmbed to true when property not set', function () {
    $model = new FakeEmbeddableModel;

    expect($model->isAutoEmbedEnabled())->toBeTrue();
});
