<?php

declare(strict_types=1);

afterEach(function () {
    $agentsDir = app_path('Agents');
    if (is_dir($agentsDir)) {
        array_map('unlink', glob("{$agentsDir}/*.php") ?: []);
        rmdir($agentsDir);
    }
});

it('creates an agent class', function () {
    $this->artisan('make:agent', ['name' => 'TestAgent'])
        ->assertExitCode(0);

    $this->assertFileExists(app_path('Agents/TestAgent.php'));

    $content = file_get_contents(app_path('Agents/TestAgent.php'));

    expect($content)
        ->toContain('namespace App\Agents;')
        ->toContain('use Atlasphp\Atlas\Agents\Agent;')
        ->toContain('class TestAgent extends Agent')
        ->toContain('public function instructions(): ?string');
});

it('creates an agent with tools stub', function () {
    $this->artisan('make:agent', [
        'name' => 'ToolsAgent',
        '--tools' => true,
    ])->assertExitCode(0);

    $content = file_get_contents(app_path('Agents/ToolsAgent.php'));

    expect($content)
        ->toContain('public function instructions(): ?string')
        ->toContain('public function tools(): array');
});

it('creates an agent with provider tools stub', function () {
    $this->artisan('make:agent', [
        'name' => 'ProviderAgent',
        '--provider-tools' => true,
    ])->assertExitCode(0);

    $content = file_get_contents(app_path('Agents/ProviderAgent.php'));

    expect($content)
        ->toContain('public function providerTools(): array')
        ->toContain('use Atlasphp\Atlas\Providers\Tools\WebSearch;');
});

it('creates a full agent with both tool stubs', function () {
    $this->artisan('make:agent', [
        'name' => 'FullAgent',
        '--tools' => true,
        '--provider-tools' => true,
    ])->assertExitCode(0);

    $content = file_get_contents(app_path('Agents/FullAgent.php'));

    expect($content)
        ->toContain('public function tools(): array')
        ->toContain('public function providerTools(): array')
        ->toContain('use Atlasphp\Atlas\Providers\Tools\WebSearch;');
});

it('does not overwrite existing agent without force', function () {
    $this->artisan('make:agent', ['name' => 'ExistingAgent'])
        ->assertExitCode(0);

    file_put_contents(app_path('Agents/ExistingAgent.php'), '<?php // custom content');

    $this->artisan('make:agent', ['name' => 'ExistingAgent'])
        ->assertExitCode(0);

    $content = file_get_contents(app_path('Agents/ExistingAgent.php'));

    expect($content)->toContain('// custom content');
});

it('overwrites existing agent with force', function () {
    $this->artisan('make:agent', ['name' => 'ForceAgent'])
        ->assertExitCode(0);

    $this->assertFileExists(app_path('Agents/ForceAgent.php'));

    $this->artisan('make:agent', [
        'name' => 'ForceAgent',
        '--force' => true,
    ])->assertExitCode(0);

    $this->assertFileExists(app_path('Agents/ForceAgent.php'));
});
