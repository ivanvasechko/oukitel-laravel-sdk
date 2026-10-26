<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use IvanVasechko\Oukitel\DTOs\Device;
use IvanVasechko\Oukitel\DTOs\DeviceId;
use IvanVasechko\Oukitel\Laravel\Facades\Oukitel;
use IvanVasechko\Oukitel\Laravel\Tests\TestCase;

uses(TestCase::class);

it('executes every public PHP example offline', function (): void {
    Http::preventStrayRequests();
    $readme = file_get_contents(dirname(__DIR__, 2).'/README.md');
    preg_match_all('/```php\n(.*?)\n```/s', $readme, $matches);
    expect($matches[1])->not->toBeEmpty();
    foreach ($matches[1] as $example) {
        $id = new DeviceId('synthetic-device-ABCD', 'synthetic-product');
        Oukitel::fake()->withDevices([new Device($id, 'Synthetic device', null, true)])->withTelemetry($id, 'online');
        (static function (string $source): void {
            eval($source);
        })($example);
    }
    Http::assertNothingSent();
});
