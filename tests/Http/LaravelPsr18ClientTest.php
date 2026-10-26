<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Request;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use IvanVasechko\Oukitel\Config\ClientOptions;
use IvanVasechko\Oukitel\Laravel\Http\LaravelPsr18Client;
use IvanVasechko\Oukitel\Laravel\Http\NetworkFailed;
use IvanVasechko\Oukitel\Laravel\Tests\TestCase;

uses(TestCase::class);

it('preserves exact wire data and honors fakes installed after construction', function (): void {
    $adapter = new LaravelPsr18Client(app(Factory::class), new ClientOptions(timeoutSeconds: 17, connectTimeoutSeconds: 4));
    Http::preventStrayRequests();
    Http::fake(['https://cloud.example.test/path?a=one%20two' => function ($request, array $options) {
        expect($request->body())->toBe('pwd=a%2Bb&email=user%40example.test');
        expect($request->method())->toBe('POST');
        expect($request->header('X-Example'))->toBe(['one', 'two']);
        expect($options)->toMatchArray(['timeout' => 17, 'connect_timeout' => 4, 'allow_redirects' => false, 'http_errors' => false]);

        return Http::response('failure-body', 503, ['X-Reply' => 'example']);
    }]);
    $response = $adapter->sendRequest(new Request('POST', 'https://cloud.example.test/path?a=one%20two',
        ['Content-Type' => 'application/x-www-form-urlencoded', 'X-Example' => ['one', 'two']], 'pwd=a%2Bb&email=user%40example.test'));
    expect($response->getStatusCode())->toBe(503);
    expect((string) $response->getBody())->toBe('failure-body');
    Http::assertSentCount(1);
});

it('does not follow redirects or throw on client errors', function (int $status): void {
    Http::preventStrayRequests();
    Http::fake(['https://cloud.example.test/read' => Http::response('', $status, ['Location' => 'https://other.example.test'])]);
    $client = new LaravelPsr18Client(app(Factory::class), new ClientOptions);
    expect($client->sendRequest(new Request('GET', 'https://cloud.example.test/read'))->getStatusCode())->toBe($status);
    Http::assertSentCount(1);
})->with([302, 401, 429]);

it('translates connection failures without retaining secret exception context', function (): void {
    Http::preventStrayRequests();
    Http::fake(['https://cloud.example.test/read' => Http::failedConnection('secret-transport-detail')]);
    $request = new Request('GET', 'https://cloud.example.test/read', ['Authorization' => 'secret-token']);
    $client = new LaravelPsr18Client(app(Factory::class), new ClientOptions);
    try {
        $client->sendRequest($request);
        test()->fail('Expected network failure.');
    } catch (NetworkFailed $failure) {
        expect($failure->getRequest())->toBe($request);
        expect($failure->getPrevious())->toBeNull();
        expect(print_r($failure, true))->not->toContain('secret-token', 'secret-transport-detail');
    }
});

it('preserves native stray-request prevention', function (): void {
    Http::preventStrayRequests();
    $client = new LaravelPsr18Client(app(Factory::class), new ClientOptions);
    expect(fn () => $client->sendRequest(new Request('GET', 'https://cloud.example.test/stray')))
        ->toThrow(RuntimeException::class);
    Http::assertNothingSent();
});

it('does not exhaust reusable native fake response bodies', function (): void {
    Http::preventStrayRequests();
    Http::fake(['https://cloud.example.test/read' => Http::response('synthetic-body')]);
    $client = new LaravelPsr18Client(app(Factory::class), new ClientOptions);
    $request = new Request('GET', 'https://cloud.example.test/read');
    $first = $client->sendRequest($request);
    expect($first->getBody()->getContents())->toBe('synthetic-body');
    $first->getBody()->close();
    expect($client->sendRequest($request)->getBody()->getContents())->toBe('synthetic-body');
});
