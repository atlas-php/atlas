<?php

declare(strict_types=1);

namespace App\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Reset the sandbox environment: fresh database + clear storage.
 */
class FreshCommand extends Command
{
    /** @var string */
    protected $signature = 'sandbox:fresh';

    /** @var string */
    protected $description = 'Reset sandbox: fresh database + clear storage';

    public function handle(Filesystem $files): void
    {
        $this->ensureSqliteDatabase($files);

        $this->call('migrate:fresh');

        User::firstOrCreate(
            ['email' => 'sandbox@atlas.test'],
            ['name' => 'Sandbox User'],
        );

        $this->line('Seeded default user (id: 1)');

        $storagePath = storage_path();

        foreach (['app', 'assets', 'outputs'] as $dir) {
            $path = $storagePath.'/'.$dir;

            if (is_dir($path)) {
                $files->cleanDirectory($path);
                $this->line("Cleared: {$dir}/");
            }
        }

        $providerPath = $storagePath.'/providers';

        if (is_dir($providerPath)) {
            $files->cleanDirectory($providerPath);
            $this->line('Cleared: providers/');
        }

        $this->info('Sandbox reset complete.');
    }

    /**
     * Create the SQLite database file if the sqlite driver is in use and the
     * file is missing — so the sandbox works with zero database setup.
     */
    protected function ensureSqliteDatabase(Filesystem $files): void
    {
        if (config('database.default') !== 'sqlite') {
            return;
        }

        $path = config('database.connections.sqlite.database');

        if (is_string($path) && $path !== ':memory:' && ! $files->exists($path)) {
            $files->ensureDirectoryExists(dirname($path));
            $files->put($path, '');
            $this->line('Created SQLite database: '.$path);
        }
    }
}
