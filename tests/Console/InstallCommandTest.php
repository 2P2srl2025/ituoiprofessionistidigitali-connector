<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;

beforeEach(function (): void {
    // The publish paths are fixed when the provider boots, before the base path changes.
    $this->publishedConfig = config_path('platform.php');
    $this->basePath = sys_get_temp_dir().'/platform-install-'.uniqid();
    new Filesystem()->ensureDirectoryExists($this->basePath);
    $this->app->setBasePath($this->basePath);
});

afterEach(function (): void {
    new Filesystem()->deleteDirectory($this->basePath);
    new Filesystem()->delete($this->publishedConfig);
});

it('publishes the configuration and writes the agent rules in the project root', function (): void {
    $this->artisan('platform:install')->assertSuccessful();

    expect($this->publishedConfig)->toBeFile()
        ->and(file_get_contents($this->basePath.'/AGENTS-platform-connector.md'))->toBe(file_get_contents(__DIR__.'/../../AGENTS.md'));
});

it('writes the agent rules in .ai/rules when the project has it', function (): void {
    mkdir($this->basePath.'/.ai/rules', recursive: true);

    $this->artisan('platform:install')->assertSuccessful();

    expect($this->basePath.'/.ai/rules/platform-connector.md')->toBeFile();
});

it('leaves existing rules alone unless forced', function (): void {
    file_put_contents($this->basePath.'/AGENTS-platform-connector.md', 'Mine');

    $this->artisan('platform:install')->assertSuccessful();
    expect(file_get_contents($this->basePath.'/AGENTS-platform-connector.md'))->toBe('Mine');

    $this->artisan('platform:install', ['--force' => true])->assertSuccessful();
    expect(file_get_contents($this->basePath.'/AGENTS-platform-connector.md'))->not->toBe('Mine');
});
