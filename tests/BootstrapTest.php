<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use IvanVasechko\Oukitel\Laravel\Tests\TestCase;

uses(TestCase::class);

it('boots Laravel through Testbench without runtime package code', function (): void {
    expect($this->app)->toBeInstanceOf(Application::class);
});

it('ships an unofficial package with its licence and required notice', function (): void {
    $root = dirname(__DIR__);
    $manifest = json_decode(file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['name'])->toBe('ivanvasechko/oukitel-laravel-sdk')
        ->and($manifest['description'])->toStartWith('Unofficial')
        ->and($manifest['license'])->toBe('PolyForm-Noncommercial-1.0.0')
        ->and(file_get_contents($root.'/LICENSE'))
        ->toStartWith('# PolyForm Noncommercial License 1.0.0')
        ->toContain('Required Notice: Copyright (c) Ivan Vasechko');
});
