<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Support;

use Illuminate\Support\Facades\File;
use ReflectionClass;
use Throwable;

/**
 * Discovers classes from filesystem paths using PSR-4 autoloading.
 *
 * Scans directories for PHP files and uses PSR-4 naming conventions
 * to identify classes that implement a given interface.
 */
class ClassDiscovery
{
    /**
     * Discover classes in a directory that implement a given interface.
     *
     * Uses PSR-4 conventions to infer class names from file paths,
     * leveraging Composer's autoloader rather than parsing file contents.
     *
     * @param  string  $path  The directory path to scan.
     * @param  string  $namespace  The base namespace for classes in this directory.
     * @param  class-string  $interface  The interface or base class to match.
     * @return array<int, class-string> Array of fully qualified class names.
     */
    public function discover(string $path, string $namespace, string $interface): array
    {
        if (! File::isDirectory($path)) {
            return [];
        }

        $classes = [];
        $basePath = realpath($path);

        foreach (File::allFiles($path) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $class = $this->inferClassName(
                $file->getPathname(),
                $basePath,
                $namespace,
            );

            if ($this->implementsInterface($class, $interface)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * Infer the fully qualified class name from a file path using PSR-4 conventions.
     *
     * @param  string  $filepath  The full path to the PHP file.
     * @param  string  $basePath  The base directory path.
     * @param  string  $namespace  The base namespace for the directory.
     * @return class-string The inferred fully qualified class name.
     */
    protected function inferClassName(string $filepath, string $basePath, string $namespace): string
    {
        $realPath = realpath($filepath);
        $relativePath = str_replace($basePath, '', $realPath);
        $relativePath = trim($relativePath, DIRECTORY_SEPARATOR);
        $relativePath = str_replace('.php', '', $relativePath);
        $relativePath = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);

        return rtrim($namespace, '\\').'\\'.$relativePath;
    }

    /**
     * Check if a class implements or extends a given interface/class.
     *
     * Uses the autoloader to load the class, then reflection to check.
     *
     * @param  class-string  $class  The class to check.
     * @param  class-string  $interface  The interface or base class.
     */
    protected function implementsInterface(string $class, string $interface): bool
    {
        try {
            // Trigger autoloading
            if (! class_exists($class, true)) {
                return false;
            }

            $reflection = new ReflectionClass($class);

            // Skip abstract classes and interfaces
            if ($reflection->isAbstract() || $reflection->isInterface()) {
                return false;
            }

            return $reflection->implementsInterface($interface)
                || $reflection->isSubclassOf($interface);
        } catch (Throwable) {
            return false;
        }
    }
}
