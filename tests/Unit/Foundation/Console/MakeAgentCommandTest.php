<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->generatedPath = app_path('Agents/CustomerSupport.php');
});

afterEach(function () {
    if (File::exists($this->generatedPath)) {
        File::delete($this->generatedPath);
    }

    if (File::isDirectory(app_path('Agents')) && File::allFiles(app_path('Agents')) === []) {
        File::deleteDirectory(app_path('Agents'));
    }
});

test('it creates an agent file in the correct directory', function () {
    $this->artisan('make:agent', ['name' => 'CustomerSupport'])
        ->assertExitCode(0);

    expect(File::exists($this->generatedPath))->toBeTrue();
});

test('generated file has correct namespace and class name', function () {
    $this->artisan('make:agent', ['name' => 'CustomerSupport'])
        ->assertExitCode(0);

    $content = File::get($this->generatedPath);

    expect($content)
        ->toContain('namespace App\\Agents;')
        ->toContain('class CustomerSupport extends AgentDefinition');
});

test('generated file includes strict types declaration', function () {
    $this->artisan('make:agent', ['name' => 'CustomerSupport'])
        ->assertExitCode(0);

    $content = File::get($this->generatedPath);

    expect($content)->toContain('declare(strict_types=1);');
});

test('generated file has correct imports', function () {
    $this->artisan('make:agent', ['name' => 'CustomerSupport'])
        ->assertExitCode(0);

    $content = File::get($this->generatedPath);

    expect($content)
        ->toContain('use Atlasphp\\Atlas\\Agents\\AgentDefinition;');
});

test('generated file includes systemPrompt and tools methods', function () {
    $this->artisan('make:agent', ['name' => 'CustomerSupport'])
        ->assertExitCode(0);

    $content = File::get($this->generatedPath);

    expect($content)
        ->toContain('public function systemPrompt(): ?string')
        ->toContain('public function tools(): array');
});

test('force flag overwrites existing file', function () {
    $this->artisan('make:agent', ['name' => 'CustomerSupport'])
        ->assertExitCode(0);

    $this->artisan('make:agent', ['name' => 'CustomerSupport', '--force' => true])
        ->assertExitCode(0);

    expect(File::exists($this->generatedPath))->toBeTrue();
});
