<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Laravel\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use IvanVasechko\Oukitel\Config\ClientOptions;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SensitiveParameter;

final readonly class LaravelPsr18Client implements ClientInterface
{
    public function __construct(
        private Factory $http,
        private ClientOptions $options,
    ) {}

    public function sendRequest(#[SensitiveParameter] RequestInterface $request): ResponseInterface
    {
        try {
            return $this->http->createPendingRequest()
                ->async(false)
                ->connectTimeout($this->options->connectTimeoutSeconds)
                ->timeout($this->options->timeoutSeconds)
                ->withOptions(['allow_redirects' => false, 'http_errors' => false])
                ->send($request->getMethod(), (string) $request->getUri(), [
                    'headers' => $request->getHeaders(),
                    'body' => (string) $request->getBody(),
                ])->toPsrResponse();
        } catch (ConnectionException) {
            throw new NetworkFailed($request);
        }
    }
}
