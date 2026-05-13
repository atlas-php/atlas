<?php

declare(strict_types=1);

use Atlasphp\Atlas\Embeddings\EmbeddingResolver;
use Atlasphp\Atlas\Embeddings\VectorQueryMacros;
use Atlasphp\Atlas\Tools\SimilaritySearch;
use Atlasphp\Atlas\Tools\ToolDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

it('has default name similarity_search', function () {
    $tool = new SimilaritySearch;

    expect($tool->name())->toBe('similarity_search');
});

it('overrides name with withName', function () {
    $tool = (new SimilaritySearch)->withName('search_docs');

    expect($tool->name())->toBe('search_docs');
});

it('has a sensible default description', function () {
    $tool = new SimilaritySearch;

    expect($tool->description())->toBe('Search for similar content using semantic similarity.');
});

it('overrides description with withDescription', function () {
    $tool = (new SimilaritySearch)->withDescription('Search internal documentation.');

    expect($tool->description())->toBe('Search internal documentation.');
});

it('returns query parameter in parameters', function () {
    $tool = new SimilaritySearch;
    $params = $tool->parameters();

    expect($params)->toHaveCount(1);
    expect($params[0]->name())->toBe('query');
});

it('invokes the using callback with query argument', function () {
    $received = null;

    $tool = new SimilaritySearch(using: function (string $query) use (&$received) {
        $received = $query;

        return ['result1', 'result2'];
    });

    $result = $tool->handle(['query' => 'best practices'], []);

    expect($received)->toBe('best practices');
    expect($result)->toBe(['result1', 'result2']);
});

it('throws RuntimeException when no callback provided', function () {
    $tool = new SimilaritySearch;

    $tool->handle(['query' => 'test'], []);
})->throws(RuntimeException::class, 'No search callback provided');

it('produces valid ToolDefinition', function () {
    $tool = (new SimilaritySearch(using: fn (string $q) => []))
        ->withName('search_faq')
        ->withDescription('Search FAQ entries.');

    $definition = $tool->toDefinition();

    expect($definition)->toBeInstanceOf(ToolDefinition::class);
    expect($definition->name)->toBe('search_faq');
    expect($definition->description)->toBe('Search FAQ entries.');
    expect($definition->parameters['properties'])->toHaveKey('query');
    expect($definition->parameters['required'])->toBe(['query']);
});

it('creates tool from usingModel factory', function () {
    $tool = SimilaritySearch::usingModel(
        Model::class,
        'embedding',
    )->withName('search_models');

    expect($tool)->toBeInstanceOf(SimilaritySearch::class);
    expect($tool->name())->toBe('search_models');
    expect($tool->description())->toContain('Model');
});

it('usingModel description uses short class name', function () {
    $tool = SimilaritySearch::usingModel(Model::class);

    expect($tool->description())->toBe('Search Model records by semantic similarity.');
});

it('usingModel uses resolve when no custom provider or model', function () {
    config(['database.default' => 'pgsql']);
    VectorQueryMacros::register();

    $vector = [0.1, 0.2, 0.3];

    $resolver = Mockery::mock(EmbeddingResolver::class);
    $resolver->shouldReceive('resolve')
        ->with('search text')
        ->once()
        ->andReturn($vector);
    $resolver->shouldNotReceive('resolveUsing');

    app()->instance(EmbeddingResolver::class, $resolver);

    $tool = SimilaritySearch::usingModel(Model::class);

    try {
        $tool->handle(['query' => 'search text'], []);
    } catch (Throwable) {
        // DB query will fail on SQLite — we only care that the resolver was called correctly
    }
});

it('usingModel uses resolveUsing when embedProvider is set', function () {
    config(['database.default' => 'pgsql']);
    VectorQueryMacros::register();

    $vector = [0.4, 0.5, 0.6];

    $resolver = Mockery::mock(EmbeddingResolver::class);
    $resolver->shouldReceive('resolveUsing')
        ->with('query text', 'openai', null)
        ->once()
        ->andReturn($vector);
    $resolver->shouldNotReceive('resolve');

    app()->instance(EmbeddingResolver::class, $resolver);

    $tool = SimilaritySearch::usingModel(Model::class, embedProvider: 'openai');

    try {
        $tool->handle(['query' => 'query text'], []);
    } catch (Throwable) {
    }
});

it('usingModel uses resolveUsing when embedModel is set', function () {
    config(['database.default' => 'pgsql']);
    VectorQueryMacros::register();

    $vector = [0.7, 0.8, 0.9];

    $resolver = Mockery::mock(EmbeddingResolver::class);
    $resolver->shouldReceive('resolveUsing')
        ->with('query text', null, 'text-embedding-3-large')
        ->once()
        ->andReturn($vector);
    $resolver->shouldNotReceive('resolve');

    app()->instance(EmbeddingResolver::class, $resolver);

    $tool = SimilaritySearch::usingModel(Model::class, embedModel: 'text-embedding-3-large');

    try {
        $tool->handle(['query' => 'query text'], []);
    } catch (Throwable) {
    }
});

it('usingModel uses resolveUsing when both embedProvider and embedModel are set', function () {
    config(['database.default' => 'pgsql']);
    VectorQueryMacros::register();

    $vector = [0.1, 0.2];

    $resolver = Mockery::mock(EmbeddingResolver::class);
    $resolver->shouldReceive('resolveUsing')
        ->with('test', 'anthropic', 'voyage-3')
        ->once()
        ->andReturn($vector);

    app()->instance(EmbeddingResolver::class, $resolver);

    $tool = SimilaritySearch::usingModel(
        Model::class,
        embedProvider: 'anthropic',
        embedModel: 'voyage-3',
    );

    try {
        $tool->handle(['query' => 'test'], []);
    } catch (Throwable) {
    }
});

it('usingModel applies custom query callback', function () {
    config(['database.default' => 'pgsql']);
    VectorQueryMacros::register();

    $callbackInvoked = false;

    $resolver = Mockery::mock(EmbeddingResolver::class);
    $resolver->shouldReceive('resolve')->andReturn([0.1, 0.2, 0.3]);
    app()->instance(EmbeddingResolver::class, $resolver);

    // Use an anonymous concrete model — Model::class is abstract
    $concreteModel = get_class(new class extends Model
    {
        protected $table = 'test_items';
    });

    $tool = SimilaritySearch::usingModel(
        $concreteModel,
        query: function ($builder) use (&$callbackInvoked) {
            $callbackInvoked = true;
        },
    );

    try {
        $tool->handle(['query' => 'test'], []);
    } catch (Throwable) {
    }

    expect($callbackInvoked)->toBeTrue();
});

it('usingModel allows chaining withName and withDescription', function () {
    $tool = SimilaritySearch::usingModel(Model::class)
        ->withName('faq_search')
        ->withDescription('Search the FAQ.');

    expect($tool->name())->toBe('faq_search')
        ->and($tool->description())->toBe('Search the FAQ.');
});

it('usingModel legacy path short-circuits to empty Collection when ids is an empty array', function () {
    // The legacy path must skip the embedding API entirely when the caller
    // signals "search nothing" via ids=[]. Verifies the contract documented
    // in the SimilaritySearch::usingModel $ids parameter docblock.
    $resolver = Mockery::mock(EmbeddingResolver::class);
    $resolver->shouldNotReceive('resolve');
    $resolver->shouldNotReceive('resolveUsing');
    app()->instance(EmbeddingResolver::class, $resolver);

    $concreteModel = get_class(new class extends Model
    {
        protected $table = 'test_empty_ids_items';
    });

    // embedProvider forces the legacy path.
    $tool = SimilaritySearch::usingModel(
        $concreteModel,
        embedProvider: 'openai',
        ids: [],
    );

    $result = $tool->handle(['query' => 'anything'], []);

    expect($result)->toBeInstanceOf(Collection::class);
    expect($result)->toHaveCount(0);
});

it('usingModel legacy path wraps a single id in whereIn on the primary key', function () {
    config(['database.default' => 'pgsql']);
    VectorQueryMacros::register();

    $resolver = Mockery::mock(EmbeddingResolver::class);
    $resolver->shouldReceive('resolveUsing')->andReturn([0.1, 0.2]);
    app()->instance(EmbeddingResolver::class, $resolver);

    // No-op vector macros so the query reaches the user's $query callback
    // with the ids whereIn already applied.
    Builder::macro('whereVectorSimilarTo', fn ($column, $embedding, $minSimilarity = 0.5) => $this);
    Builder::macro('orderByVectorDistance', fn ($column, $embedding, $direction = 'asc') => $this);

    $captured = null;

    $concreteModel = get_class(new class extends Model
    {
        protected $table = 'test_single_id_items';
    });

    $tool = SimilaritySearch::usingModel(
        $concreteModel,
        embedProvider: 'openai',
        ids: 42,
        query: function ($builder) use (&$captured) {
            $captured = $builder;
        },
    );

    try {
        $tool->handle(['query' => 'q'], []);
    } catch (Throwable) {
        // DB query throws on SQLite (table missing) — we only need the
        // builder snapshot the query callback captured before that.
    }

    expect($captured)->not->toBeNull();
    $wheres = $captured->getQuery()->wheres;
    $idWhere = collect($wheres)->firstWhere('column', 'id');

    expect($idWhere)->not->toBeNull();
    expect($idWhere['type'])->toBe('In');
    expect($idWhere['values'])->toBe([42]);
});

it('usingModel legacy path passes an ids array straight through to whereIn', function () {
    config(['database.default' => 'pgsql']);
    VectorQueryMacros::register();

    $resolver = Mockery::mock(EmbeddingResolver::class);
    $resolver->shouldReceive('resolveUsing')->andReturn([0.1, 0.2]);
    app()->instance(EmbeddingResolver::class, $resolver);

    Builder::macro('whereVectorSimilarTo', fn ($column, $embedding, $minSimilarity = 0.5) => $this);
    Builder::macro('orderByVectorDistance', fn ($column, $embedding, $direction = 'asc') => $this);

    $captured = null;

    $concreteModel = get_class(new class extends Model
    {
        protected $table = 'test_array_ids_items';
    });

    $tool = SimilaritySearch::usingModel(
        $concreteModel,
        embedProvider: 'openai',
        ids: [1, 2, 5, 8, 13],
        query: function ($builder) use (&$captured) {
            $captured = $builder;
        },
    );

    try {
        $tool->handle(['query' => 'q'], []);
    } catch (Throwable) {
    }

    expect($captured)->not->toBeNull();
    $wheres = $captured->getQuery()->wheres;
    $idWhere = collect($wheres)->firstWhere('column', 'id');

    expect($idWhere)->not->toBeNull();
    expect($idWhere['type'])->toBe('In');
    expect($idWhere['values'])->toBe([1, 2, 5, 8, 13]);
});

it('usingModel legacy path skips the ids whereIn when ids is null', function () {
    // Complements the empty-array short-circuit and single/array tests:
    // null is the "no scoping" default and must NOT add a whereIn clause
    // on the primary key.
    config(['database.default' => 'pgsql']);
    VectorQueryMacros::register();

    $resolver = Mockery::mock(EmbeddingResolver::class);
    $resolver->shouldReceive('resolveUsing')->andReturn([0.1, 0.2]);
    app()->instance(EmbeddingResolver::class, $resolver);

    Builder::macro('whereVectorSimilarTo', fn ($column, $embedding, $minSimilarity = 0.5) => $this);
    Builder::macro('orderByVectorDistance', fn ($column, $embedding, $direction = 'asc') => $this);

    $captured = null;

    $concreteModel = get_class(new class extends Model
    {
        protected $table = 'test_null_ids_items';
    });

    $tool = SimilaritySearch::usingModel(
        $concreteModel,
        embedProvider: 'openai',
        ids: null,
        query: function ($builder) use (&$captured) {
            $captured = $builder;
        },
    );

    try {
        $tool->handle(['query' => 'q'], []);
    } catch (Throwable) {
    }

    expect($captured)->not->toBeNull();
    $idWhere = collect($captured->getQuery()->wheres)->firstWhere('column', 'id');
    expect($idWhere)->toBeNull();
});

it('usingModel legacy path calls whereVectorSimilarTo when min_similarity is supplied', function () {
    config(['database.default' => 'pgsql']);
    VectorQueryMacros::register();

    $resolver = Mockery::mock(EmbeddingResolver::class);
    $resolver->shouldReceive('resolveUsing')->andReturn([0.1, 0.2, 0.3]);
    app()->instance(EmbeddingResolver::class, $resolver);

    // Capture whereVectorSimilarTo / orderByVectorDistance invocations on
    // the builder so we can verify which branch the legacy path took.
    $calls = [];
    Builder::macro(
        'whereVectorSimilarTo',
        function (string $column, mixed $embedding, float $minSimilarity = 0.5) use (&$calls) {
            $calls[] = ['where', $column, $embedding, $minSimilarity];

            return $this;
        },
    );
    Builder::macro(
        'orderByVectorDistance',
        function (string $column, mixed $embedding, string $direction = 'asc') use (&$calls) {
            $calls[] = ['order', $column, $embedding, $direction];

            return $this;
        },
    );

    $concreteModel = get_class(new class extends Model
    {
        protected $table = 'test_min_similarity_items';
    });

    // embedProvider forces the legacy path even though the model has no
    // standard atlas trait.
    $tool = SimilaritySearch::usingModel(
        $concreteModel,
        column: 'embedding',
        minSimilarity: 0.42,
        embedProvider: 'openai',
    );

    try {
        $tool->handle(['query' => 'hello'], []);
    } catch (Throwable) {
        // DB query will fail on SQLite — only the macro recording matters.
    }

    expect($calls)->toHaveCount(1);
    expect($calls[0][0])->toBe('where');
    expect($calls[0][1])->toBe('embedding');
    expect($calls[0][2])->toBe([0.1, 0.2, 0.3]);
    expect($calls[0][3])->toBe(0.42);
});
