<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use IvanVasechko\Oukitel\DTOs\Device;
use IvanVasechko\Oukitel\DTOs\DeviceId;
use IvanVasechko\Oukitel\DTOs\Telemetry;
use IvanVasechko\Oukitel\DTOs\TslAttribute;
use IvanVasechko\Oukitel\Laravel\Console\ProbeOukitelCommand;
use IvanVasechko\Oukitel\Laravel\Facades\Oukitel;
use IvanVasechko\Oukitel\Laravel\Tests\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

uses(TestCase::class);

/** @param array<string, mixed> $options
 * @return array{int, string}
 */
function executeSyntheticProbe(array $options, string $stdin = ''): array
{
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $stdin);
    rewind($stream);
    $input = new ArrayInput($options);
    $input->setInteractive(false);
    $input->setStream($stream);
    $output = new BufferedOutput;
    $command = app(ProbeOukitelCommand::class);
    $command->setLaravel(app());
    try {
        return [$command->run($input, $output), $output->fetch()];
    } finally {
        fclose($stream);
    }
}

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('lists devices without publishing names, accounts or full identifiers', function (): void {
    $fake = Oukitel::fake()->withDevices([
        new Device(new DeviceId('synthetic-secret-ABCD', 'private-product'), 'private-device-name', 'private-model', true),
    ]);
    [$status, $output] = executeSyntheticProbe(['--credentials-stdin' => true], "reader@example.test\nprivate-password\n");
    expect($status)->toBe(0);
    expect($output)->toContain('1: Device ****ABCD (online)');
    expect($output)->not->toContain('reader@example.test', 'private-password', 'private-device-name', 'private-model', 'synthetic-secret', 'private-product');
    $fake->assertNoControlsSent();
    Http::assertNothingSent();
});

it('samples telemetry with explicit single wake and fakeable bounded waits', function (): void {
    Sleep::fake();
    $id = new DeviceId('synthetic-device-ABCD', 'synthetic-product');
    $fake = Oukitel::fake()->withDevices([new Device($id, 'private-name', null, null)])
        ->withTelemetry($id, new Telemetry(batteryPercent: 52, extra: ['private-token' => 'private-value']))
        ->withRawAttributes($id, [
            new TslAttribute('battery_percentage', '52', 'INT'),
            new TslAttribute('AC_Info', '{"power":42,"deviceName":"private-name","unknown":"private-value"}', 'STRUCT'),
            new TslAttribute('private-code', ['password' => 'private-value'], 'private-type'),
        ]);
    [$status, $output] = executeSyntheticProbe([
        '--login' => 'reader@example.test', '--password-stdin' => true, '--device' => 'ABCD',
        '--watch' => '2', '--count' => '3', '--raw' => true, '--capabilities' => true, '--wake' => true,
    ], "private-password\n");
    expect($status)->toBe(0);
    expect(substr_count($output, '"sample"'))->toBe(3);
    expect($output)->toContain('"batteryPercent": 52', '"power": 42', '[redacted]', '[unknown]');
    expect($output)->not->toContain('private-name', 'private-value', 'private-password', 'private-code', 'private-type');
    expect($fake->cloud()->wakes())->toHaveCount(1);
    $fake->assertWoke($id);
    $fake->assertNoControlsSent();
    Sleep::assertSleptTimes(2);
    Http::assertNothingSent();
});

it('validates options before credentials and any SDK resolution', function (array $options): void {
    [$status, $output] = executeSyntheticProbe($options);
    expect($status)->toBe(1);
    expect($output)->not->toContain('Oukitel request failed');
    Http::assertNothingSent();
})->with([
    [['--region' => 'bad']], [['--count' => '2']], [['--watch' => '0']], [['--watch' => '-1']],
    [['--watch' => 'abc']], [['--watch' => '1', '--count' => '0', '--device' => '1']],
    [['--wake' => true]], [['--raw' => true]], [['--capabilities' => true]],
    [['--device' => '<private>']], [['--credentials-stdin' => true, '--password-stdin' => true]],
    [['--credentials-from-config' => true, '--login' => 'private-login']],
    [['--password-stdin' => true]],
]);

it('rejects missing stdin and never implicitly consumes configured credentials', function (array $options, string $stdin): void {
    config()->set('oukitel.probe', ['login' => 'configured@example.test', 'password' => 'private-password']);
    [$status, $output] = executeSyntheticProbe($options, $stdin);
    expect($status)->toBe(1);
    expect($output)->not->toContain('configured@example.test', 'private-password');
    Http::assertNothingSent();
})->with([
    [[], ''], [['--credentials-stdin' => true], ''],
    [['--credentials-stdin' => true], "reader@example.test\n"],
    [['--credentials-stdin' => true], "reader@example.test\n\n"],
]);

it('supports explicit config credentials and a single read without implicit wake', function (): void {
    config()->set('oukitel.probe', ['login' => 'reader@example.test', 'password' => 'private-password']);
    $id = new DeviceId('synthetic-device-ABCD', 'synthetic-product');
    $fake = Oukitel::fake()->withDevices([new Device($id, 'private-name', null, null)])->withTelemetry($id, new Telemetry(batteryPercent: 25));
    [$status, $output] = executeSyntheticProbe(['--credentials-from-config' => true, '--device' => '1']);
    expect($status)->toBe(0);
    expect(substr_count($output, '"sample"'))->toBe(1);
    $fake->assertNotWoke($id);
    Http::assertNothingSent();
});

it('rejects unknown or ambiguous suffixes', function (string $selector): void {
    Oukitel::fake()->withDevices([
        new Device(new DeviceId('synthetic-one-ABCD', 'synthetic-product'), 'first', null, null),
        new Device(new DeviceId('synthetic-two-ABCD', 'synthetic-product'), 'second', null, null),
    ]);
    [$status, $output] = executeSyntheticProbe(['--credentials-stdin' => true, '--device' => $selector], "reader@example.test\nsynthetic\n");
    expect($status)->toBe(1);
    expect($output)->toContain('missing or ambiguous');
})->with(['ABCD', 'NONE', '9']);

it('sanitizes failures from the cloud', function (): void {
    Oukitel::fake()->failLoginWith(new RuntimeException('private-password private-token reader@example.test'));
    [$status, $output] = executeSyntheticProbe(['--credentials-stdin' => true], "reader@example.test\nprivate-password\n");
    expect($status)->toBe(1);
    expect($output)->toContain('Oukitel request failed');
    expect($output)->not->toContain('private-password', 'private-token', 'reader@example.test');
});

it('uses an invisible password prompt for interactive credentials', function (): void {
    Oukitel::fake()->withDevices([]);
    $this->artisan('oukitel:probe', ['--login' => 'reader@example.test'])
        ->expectsQuestion('Account password', 'synthetic-password')
        ->expectsOutput('No devices found.')
        ->assertSuccessful();
});
