<?php

declare(strict_types=1);

afterEach(function () {
    $toolsDir = app_path('Tools');
    if (is_dir($toolsDir)) {
        array_map('unlink', glob("{$toolsDir}/*.php") ?: []);
        rmdir($toolsDir);
    }
});

it('creates a tool class', function () {
    $this->artisan('make:tool', ['name' => 'SearchTool'])
        ->assertExitCode(0);

    $this->assertFileExists(app_path('Tools/SearchTool.php'));

    $content = file_get_contents(app_path('Tools/SearchTool.php'));

    expect($content)
        ->toContain('namespace App\Tools;')
        ->toContain('use Atlasphp\Atlas\Tools\Tool;')
        ->toContain('class SearchTool extends Tool')
        ->toContain('public function name(): string')
        ->toContain('public function description(): string')
        ->toContain('public function parameters(): array')
        ->toContain('public function handle(array $args, array $context): mixed');
});

it('derives the tool name from the class name', function () {
    $this->artisan('make:tool', ['name' => 'LookupOrderTool'])
        ->assertExitCode(0);

    $content = file_get_contents(app_path('Tools/LookupOrderTool.php'));

    expect($content)->toContain("return 'lookup_order';");
});

it('derives multi-word tool names correctly', function () {
    $this->artisan('make:tool', ['name' => 'SearchKnowledgeBaseTool'])
        ->assertExitCode(0);

    $content = file_get_contents(app_path('Tools/SearchKnowledgeBaseTool.php'));

    expect($content)->toContain("return 'search_knowledge_base';");
});

it('handles tool names without Tool suffix', function () {
    $this->artisan('make:tool', ['name' => 'ProcessRefund'])
        ->assertExitCode(0);

    $content = file_get_contents(app_path('Tools/ProcessRefund.php'));

    expect($content)->toContain("return 'process_refund';");
});

it('includes Schema import and commented examples', function () {
    $this->artisan('make:tool', ['name' => 'ExampleTool'])
        ->assertExitCode(0);

    $content = file_get_contents(app_path('Tools/ExampleTool.php'));

    expect($content)
        ->toContain('use Atlasphp\Atlas\Schema\Schema;')
        ->toContain("Schema::string('query'")
        ->toContain("Schema::integer('limit'");
});

it('does not overwrite existing tool without force', function () {
    $this->artisan('make:tool', ['name' => 'ExistingTool'])
        ->assertExitCode(0);

    file_put_contents(app_path('Tools/ExistingTool.php'), '<?php // custom content');

    $this->artisan('make:tool', ['name' => 'ExistingTool'])
        ->assertExitCode(0);

    $content = file_get_contents(app_path('Tools/ExistingTool.php'));

    expect($content)->toContain('// custom content');
});

it('overwrites with force flag', function () {
    $this->artisan('make:tool', ['name' => 'ForceTool'])
        ->assertExitCode(0);

    $this->artisan('make:tool', [
        'name' => 'ForceTool',
        '--force' => true,
    ])->assertExitCode(0);

    $this->assertFileExists(app_path('Tools/ForceTool.php'));
});
