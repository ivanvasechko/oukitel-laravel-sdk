<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use IvanVasechko\Oukitel\Laravel\Facades\Oukitel;
use PHPUnit\Framework\Assert;

function distributionRun(array $command, string $directory): void
{
    $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $directory);
    if (! is_resource($process) || proc_close($process) !== 0) {
        throw new RuntimeException('Distribution consumer command failed.');
    }
}

function distributionApplication(string $directory): Application
{
    $app = require $directory.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    return $app;
}

if (($argv[1] ?? null) === '--consume') {
    try {
        $directory = $argv[2];
        require $directory.'/vendor/autoload.php';
        $app = distributionApplication($directory);
        Http::preventStrayRequests();
        Http::fake([
            '*emailPwdLogin' => Http::response(['code' => 200, 'data' => [
                'accessToken' => ['token' => 'synthetic-access', 'expirationTime' => time() + 3600],
                'refreshToken' => ['token' => 'synthetic-refresh'],
            ]]),
            '*userDeviceList' => Http::response(['code' => 200, 'data' => ['list' => []]]),
        ]);
        $app['config']->set('cache.default', 'array');
        $account = Oukitel::account(['login' => 'reader@example.test', 'password' => 'synthetic-password']);
        if ($account->devices() !== [] || $account->devices() !== []) {
            throw new RuntimeException('Archived consumer could not use native HTTP.');
        }
        $kernel = $app->make(Kernel::class);
        if ($kernel->call('vendor:publish', ['--tag' => 'oukitel-config', '--force' => true]) !== 0) {
            throw new RuntimeException('Archived configuration could not be published.');
        }
        $app['config']->set('oukitel.probe', ['login' => 'reader@example.test', 'password' => 'synthetic-password']);
        file_put_contents($directory.'/config/oukitel.php', '<?php return '.var_export($app['config']->get('oukitel'), true).';');
        if ($kernel->call('config:cache') !== 0) {
            throw new RuntimeException('Archived configuration could not be cached.');
        }
        $app = distributionApplication($directory);
        if (! $app->configurationIsCached()) {
            throw new RuntimeException('Archived consumer did not reload cached configuration.');
        }
        Oukitel::fake()->withDevices([]);
        if ($app->make(Kernel::class)->call('oukitel:probe', ['--credentials-from-config' => true, '--no-interaction' => true]) !== 0) {
            throw new RuntimeException('Archived diagnostic command failed.');
        }
        if (class_exists(Assert::class)) {
            throw new RuntimeException('Archived consumer unexpectedly depends on test packages.');
        }
        echo "Archived consumer passed.\n";

        return;
    } catch (Throwable $failure) {
        fwrite(STDERR, (string) $failure);
        exit(1);
    }
}

$major = $argv[1] ?? '';
if (! in_array($major, ['12', '13'], true)) {
    throw new InvalidArgumentException('Choose supported Laravel major 12 or 13.');
}
$root = dirname(__DIR__, 2);
$temporary = sys_get_temp_dir().'/oukitel-dist-'.bin2hex(random_bytes(8));
mkdir($temporary);
try {
    mkdir($temporary.'/archive');
    mkdir($temporary.'/source');
    mkdir($temporary.'/consumer');
    mkdir($temporary.'/consumer/bootstrap/cache', 0777, true);
    mkdir($temporary.'/consumer/config');
    file_put_contents($temporary.'/consumer/bootstrap/app.php', <<<'PHP'
<?php
return Illuminate\Foundation\Application::configure(basePath: dirname(__DIR__))
    ->withProviders([IvanVasechko\Oukitel\Laravel\OukitelServiceProvider::class])
    ->create();
PHP);
    distributionRun(['composer', 'archive', '--quiet', '--format=zip', '--dir='.$temporary.'/archive', '--file=package'], $root);
    $zip = new ZipArchive;
    if ($zip->open($temporary.'/archive/package.zip') !== true) {
        throw new RuntimeException('Package archive is unavailable.');
    }
    foreach (['vendor/', 'node_modules/', 'tests/', '.github/', 'docs/', 'composer.lock', 'package.json', 'package-lock.json', 'testbench.yaml'] as $excluded) {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (is_string($name) && str_starts_with($name, $excluded)) {
                throw new RuntimeException('Development content leaked into package archive.');
            }
        }
    }
    if (! $zip->extractTo($temporary.'/source')) {
        throw new RuntimeException('Package archive extraction failed.');
    }
    $zip->close();
    $manifest = [
        'name' => 'example/archived-consumer',
        'require' => ['laravel/framework' => '^'.$major.'.0', 'ivanvasechko/oukitel-laravel-sdk' => 'dev-distribution'],
        'repositories' => [[
            'type' => 'path', 'url' => $temporary.'/source',
            'options' => ['symlink' => false, 'versions' => ['ivanvasechko/oukitel-laravel-sdk' => 'dev-distribution']],
        ]],
        'config' => ['allow-plugins' => ['php-http/discovery' => false]],
    ];
    file_put_contents($temporary.'/consumer/composer.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    distributionRun(['composer', 'install', '--quiet', '--no-dev', '--prefer-dist', '--no-interaction'], $temporary.'/consumer');
    distributionRun(['composer', 'audit', '--quiet'], $temporary.'/consumer');
    distributionRun([PHP_BINARY, __FILE__, '--consume', $temporary.'/consumer'], $root);
} finally {
    $entries = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($entries as $entry) {
        if ($entry->isDir() && ! $entry->isLink()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($temporary);
}
