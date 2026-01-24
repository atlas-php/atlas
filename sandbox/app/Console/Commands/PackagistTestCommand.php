<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Tests the atlas-php/atlas package installation from Packagist.
 *
 * Creates a temporary Laravel project, installs the package from Packagist,
 * and verifies basic functionality works correctly.
 */
class PackagistTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'atlas:packagist-test
                            {--keep : Keep the temporary directory after testing}
                            {--release= : Specific version to install (default: latest)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test atlas-php/atlas package installation from Packagist';

    /**
     * The temporary directory path.
     */
    protected string $tempDir;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->line('');
        $this->info('=== Atlas Packagist Installation Test ===');
        $this->line('');

        // Create temp directory
        $this->tempDir = sys_get_temp_dir().'/atlas-packagist-test-'.time();

        try {
            $this->createTempDirectory();
            $this->createLaravelProject();
            $this->installAtlasPackage();
            $this->verifyInstallation();
            $this->verifyPublishedConfig();
            $this->testBasicFunctionality();

            $this->line('');
            $this->info('[PASS] All Packagist installation tests passed!');
            $this->line('');

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->line('');
            $this->error('[FAIL] '.$e->getMessage());
            $this->line('');

            return self::FAILURE;

        } finally {
            if (! $this->option('keep')) {
                $this->cleanup();
            } else {
                $this->line("Temp directory kept at: {$this->tempDir}");
            }
        }
    }

    /**
     * Create the temporary directory.
     */
    protected function createTempDirectory(): void
    {
        $this->task('Creating temporary directory', function () {
            if (! mkdir($this->tempDir, 0755, true)) {
                throw new \RuntimeException("Failed to create temp directory: {$this->tempDir}");
            }

            return true;
        });
    }

    /**
     * Create a fresh Laravel project.
     */
    protected function createLaravelProject(): void
    {
        $this->task('Creating Laravel project', function () {
            $result = Process::timeout(300)
                ->path($this->tempDir)
                ->run('composer create-project laravel/laravel . --prefer-dist --no-interaction');

            if (! $result->successful()) {
                throw new \RuntimeException('Failed to create Laravel project: '.$result->errorOutput());
            }

            return true;
        });
    }

    /**
     * Install the atlas-php/atlas package from Packagist.
     */
    protected function installAtlasPackage(): void
    {
        $version = $this->option('release');
        $package = 'atlas-php/atlas'.($version ? ':'.$version : '');

        $this->task("Installing {$package} from Packagist", function () use ($package) {
            $result = Process::timeout(300)
                ->path($this->tempDir)
                ->run("composer require {$package} --no-interaction");

            if (! $result->successful()) {
                throw new \RuntimeException('Failed to install Atlas package: '.$result->errorOutput());
            }

            return true;
        });
    }

    /**
     * Verify the package was installed correctly.
     */
    protected function verifyInstallation(): void
    {
        $this->task('Verifying package installation', function () {
            // Check composer.json has the package
            $composerJson = json_decode(
                file_get_contents($this->tempDir.'/composer.json'),
                true
            );

            if (! isset($composerJson['require']['atlas-php/atlas'])) {
                throw new \RuntimeException('Package not found in composer.json');
            }

            // Check vendor directory exists
            if (! is_dir($this->tempDir.'/vendor/atlas-php/atlas')) {
                throw new \RuntimeException('Package not found in vendor directory');
            }

            // Check config can be published
            $result = Process::timeout(60)
                ->path($this->tempDir)
                ->run('php artisan vendor:publish --tag=atlas-config --no-interaction');

            if (! $result->successful()) {
                throw new \RuntimeException('Failed to publish config: '.$result->errorOutput());
            }

            // Check config file exists
            if (! file_exists($this->tempDir.'/config/atlas.php')) {
                throw new \RuntimeException('Config file not published');
            }

            return true;
        });
    }

    /**
     * Verify the published config file has expected structure.
     */
    protected function verifyPublishedConfig(): void
    {
        $this->task('Verifying published config structure', function () {
            $configPath = $this->tempDir.'/config/atlas.php';
            $config = require $configPath;

            // Check required top-level keys exist
            $requiredKeys = ['providers', 'chat', 'embedding'];

            foreach ($requiredKeys as $key) {
                if (! array_key_exists($key, $config)) {
                    throw new \RuntimeException("Config missing required key: {$key}");
                }
            }

            // Check providers structure
            if (! isset($config['providers']['openai']) && ! isset($config['providers']['anthropic'])) {
                throw new \RuntimeException('Config missing provider definitions');
            }

            // Check chat config has provider and model
            if (! isset($config['chat']['provider']) || ! isset($config['chat']['model'])) {
                throw new \RuntimeException('Config chat section missing provider or model');
            }

            // Check embedding config has required fields
            if (! isset($config['embedding']['provider']) || ! isset($config['embedding']['model'])) {
                throw new \RuntimeException('Config embedding section missing provider or model');
            }

            return true;
        });
    }

    /**
     * Test basic package functionality.
     */
    protected function testBasicFunctionality(): void
    {
        $this->task('Testing basic functionality', function () {
            // Create a test script that verifies core classes are autoloadable
            $testScript = <<<'PHP'
<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Test core classes exist and are autoloadable
$classes = [
    \Atlasphp\Atlas\Providers\Facades\Atlas::class,
    \Atlasphp\Atlas\Agents\AgentDefinition::class,
    \Atlasphp\Atlas\Contracts\Tools\ToolDefinition::class,
];

foreach ($classes as $class) {
    if (!class_exists($class)) {
        echo "FAIL: Class not found: {$class}\n";
        exit(1);
    }
}

// Test AtlasManager is registered (facade accessor)
if (!app()->bound(\Atlasphp\Atlas\Providers\Services\AtlasManager::class)) {
    echo "FAIL: AtlasManager not bound in container\n";
    exit(1);
}

echo "OK\n";
exit(0);
PHP;

            file_put_contents($this->tempDir.'/test-atlas.php', $testScript);

            $result = Process::timeout(30)
                ->path($this->tempDir)
                ->run('php test-atlas.php');

            if (! $result->successful() || trim($result->output()) !== 'OK') {
                throw new \RuntimeException('Basic functionality test failed: '.$result->output().$result->errorOutput());
            }

            return true;
        });
    }

    /**
     * Clean up the temporary directory.
     */
    protected function cleanup(): void
    {
        $this->task('Cleaning up', function () {
            if (is_dir($this->tempDir)) {
                Process::run("rm -rf {$this->tempDir}");
            }

            return true;
        });
    }

    /**
     * Run a task with output.
     */
    protected function task(string $title, callable $callback): void
    {
        $this->output->write("  {$title}... ");

        try {
            $result = $callback();

            if ($result) {
                $this->info('done');
            } else {
                $this->error('failed');
            }
        } catch (\Throwable $e) {
            $this->error('failed');
            throw $e;
        }
    }
}
