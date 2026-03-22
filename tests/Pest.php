<?php

declare(strict_types=1);

use Atlasphp\Atlas\Tests\PersistenceTestCase;
use Atlasphp\Atlas\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

// Persistence tests use PersistenceTestCase which loads migrations and enables persistence config.
// Registered before TestCase bindings to avoid duplicate-folder conflicts.
pest()->extend(PersistenceTestCase::class)->in('Unit/Persistence');
pest()->extend(PersistenceTestCase::class)->in('Feature/Persistence');

// Feature tests — list subdirectories and individual top-level files
// to avoid conflicting with Feature/Persistence binding above.
pest()->extend(TestCase::class)->in(
    'Feature/Console',
    'Feature/Testing',
    'Feature/Variables',
    'Feature/AtlasManagerEntryPointTest.php',
    'Feature/AtlasManagerMissingDefaultTest.php',
    'Feature/AtlasServiceProviderTest.php',
    'Feature/ConfigTest.php',
    'Feature/FacadeTest.php',
    'Feature/ProviderRegistryTest.php',
);
pest()->extend(TestCase::class)->in(
    'Unit/Agents',
    'Unit/AgentTest.php',
    'Unit/AtlasServiceProviderTest.php',
    'Unit/Concerns',
    'Unit/Console',
    'Unit/Embeddings',
    'Unit/Enums',
    'Unit/Events',
    'Unit/Exceptions',
    'Unit/Executor',
    'Unit/Input',
    'Unit/Messages',
    'Unit/Middleware',
    'Unit/Pending',
    'Unit/Providers',
    'Unit/Queue',
    'Unit/Requests',
    'Unit/Responses',
    'Unit/Schema',
    'Unit/Streaming',
    'Unit/Support',
    'Unit/Testing',
    'Unit/Tools',
);
