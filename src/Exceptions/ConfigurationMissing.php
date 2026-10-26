<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Laravel\Exceptions;

use IvanVasechko\Oukitel\Exceptions\OukitelException;

final class ConfigurationMissing extends OukitelException
{
    public function __construct(string $key)
    {
        parent::__construct('Invalid Oukitel configuration: oukitel.'.$key.'.');
    }
}
