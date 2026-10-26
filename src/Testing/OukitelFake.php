<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Laravel\Testing;

use IvanVasechko\Oukitel\DTOs\Device;
use IvanVasechko\Oukitel\DTOs\DeviceId;
use IvanVasechko\Oukitel\DTOs\Telemetry;
use IvanVasechko\Oukitel\DTOs\TslAttribute;
use IvanVasechko\Oukitel\Testing\FakeOukitelCloud;
use PHPUnit\Framework\Assert;
use SensitiveParameter;
use Throwable;

final readonly class OukitelFake
{
    public function __construct(private FakeOukitelCloud $cloud) {}

    public function cloud(): FakeOukitelCloud
    {
        return $this->cloud;
    }

    /** @param list<Device> $devices */
    public function withDevices(array $devices): self
    {
        $this->cloud->withDevices($devices);

        return $this;
    }

    public function withTelemetry(#[SensitiveParameter] DeviceId $device, Telemetry|string $fixture): self
    {
        $this->cloud->withTelemetry($device, $fixture);

        return $this;
    }

    /** @param list<TslAttribute> $attributes */
    public function withRawAttributes(#[SensitiveParameter] DeviceId $device, array $attributes): self
    {
        $this->cloud->withRawAttributes($device, $attributes);

        return $this;
    }

    public function failLoginWith(Throwable $failure): self
    {
        $this->cloud->failLoginWith($failure);

        return $this;
    }

    public function failRefreshWith(Throwable $failure): self
    {
        $this->cloud->failRefreshWith($failure);

        return $this;
    }

    public function failControlWith(Throwable $failure): self
    {
        $this->cloud->failControlWith($failure);

        return $this;
    }

    public function assertWoke(#[SensitiveParameter] DeviceId $device): void
    {
        Assert::assertContainsEquals($device, $this->cloud->wakes(), 'Expected a wake for the selected device.');
    }

    public function assertNotWoke(#[SensitiveParameter] DeviceId $device): void
    {
        Assert::assertNotContainsEquals($device, $this->cloud->wakes(), 'Unexpected wake for the selected device.');
    }

    public function assertNoControlsSent(): void
    {
        Assert::assertCount(0, $this->cloud->commands(), 'Unexpected experimental control attempt.');
    }
}
