<?php

declare(strict_types=1);

namespace Atlasphp\Atlas {
    // Override glob() within the package namespace so AgentRegistry::discover()
    // can be driven into its `$files === false` guard — a branch real glob()
    // never hits on a valid directory (it returns [] for no matches). Delegates
    // to the real glob() unless the toggle is set, so it stays inert for every
    // other test in the suite.
    function glob(string $pattern, int $flags = 0): array|false
    {
        return ! empty($GLOBALS['__atlas_force_glob_false'])
            ? false
            : \glob($pattern, $flags);
    }
}

namespace {
    use Atlasphp\Atlas\AgentRegistry;

    afterEach(function (): void {
        unset($GLOBALS['__atlas_force_glob_false']);
    });

    it('returns early when glob fails on a valid directory', function () {
        $dir = sys_get_temp_dir().'/atlas-glob-false-'.uniqid();
        mkdir($dir, 0755, true);

        $registry = new AgentRegistry(app());
        $GLOBALS['__atlas_force_glob_false'] = true;

        // glob() returns false → discover() must bail before the foreach.
        $registry->discover($dir, 'App\\Agents');

        expect($registry->keys())->toBe([]);

        rmdir($dir);
    });
}
