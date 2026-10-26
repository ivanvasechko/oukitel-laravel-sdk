<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Laravel\Tests;

use IvanVasechko\Oukitel\Laravel\OukitelServiceProvider;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [OukitelServiceProvider::class];
    }
}
