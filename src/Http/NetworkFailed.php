<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Laravel\Http;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use SensitiveParameter;

final class NetworkFailed extends RuntimeException implements NetworkExceptionInterface
{
    public function __construct(#[SensitiveParameter] private readonly RequestInterface $request)
    {
        parent::__construct('Oukitel HTTP connection failed.');
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /** @return array{message: string} */
    public function __debugInfo(): array
    {
        return ['message' => $this->getMessage()];
    }
}
