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
        $this->call('migrate:fresh');

        User::create([
            'name' => 'Sandbox User',
            'email' => 'sandbox@atlas.test',
        ]);

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
}
