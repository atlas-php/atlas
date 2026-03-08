<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

afterEach(function () {
    // Clean up any generated tool files
    foreach (['SearchProducts', 'LookupOrderTool', 'Tool'] as $name) {
        $path = app_path("Tools/{$name}.php");
        if (File::exists($path)) {
            File::delete($path);
        }
    }

    // Clean up Tools directory if empty
    if (File::isDirectory(app_path('Tools')) && File::allFiles(app_path('Tools')) === []) {
        File::deleteDirectory(app_path('Tools'));
    }
});

test('it creates a tool file in the correct directory', function () {
    $this->artisan('make:tool', ['name' => 'SearchProducts'])
        ->assertExitCode(0);

    expect(File::exists(app_path('Tools/SearchProducts.php')))->toBeTrue();
});

test('generated file has correct namespace and class name', function () {
    $this->artisan('make:tool', ['name' => 'SearchProducts'])
        ->assertExitCode(0);

    $content = File::get(app_path('Tools/SearchProducts.php'));

    expect($content)
        ->toContain('namespace App\\Tools;')
        ->toContain('class SearchProducts extends ToolDefinition');
});

test('generated file includes strict types declaration', function () {
    $this->artisan('make:tool', ['name' => 'SearchProducts'])
        ->assertExitCode(0);

    $content = File::get(app_path('Tools/SearchProducts.php'));

    expect($content)->toContain('declare(strict_types=1);');
});

test('generated file has correct imports', function () {
    $this->artisan('make:tool', ['name' => 'SearchProducts'])
        ->assertExitCode(0);

    $content = File::get(app_path('Tools/SearchProducts.php'));

    expect($content)
        ->toContain('use Atlasphp\\Atlas\\Tools\\ToolDefinition;')
        ->toContain('use Atlasphp\\Atlas\\Tools\\Support\\ToolContext;')
        ->toContain('use Atlasphp\\Atlas\\Tools\\Support\\ToolParameter;')
        ->toContain('use Atlasphp\\Atlas\\Tools\\Support\\ToolResult;');
});

test('tool name is auto-generated as snake_case', function () {
    $this->artisan('make:tool', ['name' => 'SearchProducts'])
        ->assertExitCode(0);

    $content = File::get(app_path('Tools/SearchProducts.php'));

    expect($content)->toContain("return 'search_products';");
});

test('tool name strips Tool suffix before converting to snake_case', function () {
    $this->artisan('make:tool', ['name' => 'LookupOrderTool'])
        ->assertExitCode(0);

    $content = File::get(app_path('Tools/LookupOrderTool.php'));

    expect($content)->toContain("return 'lookup_order';");
});

test('class named Tool uses full name for tool name', function () {
    $this->artisan('make:tool', ['name' => 'Tool'])
        ->assertExitCode(0);

    $content = File::get(app_path('Tools/Tool.php'));

    expect($content)->toContain("return 'tool';");
});

test('force flag overwrites existing file', function () {
    $this->artisan('make:tool', ['name' => 'SearchProducts'])
        ->assertExitCode(0);

    // With --force, should succeed
    $this->artisan('make:tool', ['name' => 'SearchProducts', '--force' => true])
        ->assertExitCode(0);

    expect(File::exists(app_path('Tools/SearchProducts.php')))->toBeTrue();
});
