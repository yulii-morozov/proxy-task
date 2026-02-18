<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Builder\ProxyResponseBuilderInterface;
use App\Controller\ProxyController;
use App\DTO\ProxyRequest;
use App\DTO\ProxyResponse;
use App\Resolver\ProxyRequestResolverInterface;
use App\Service\CorsService\CorsServiceInterface;
use App\Service\ProxyErrorHandler\ProxyErrorHandlerInterface;
use App\Service\ProxyService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProxyControllerTest extends TestCase
{
    private ProxyService&MockObject $proxyService;
    private ProxyRequestResolverInterface&MockObject $proxyRequestResolver;
    private ProxyResponseBuilderInterface&MockObject $proxyResponseBuilder;
    private CorsServiceInterface&MockObject $corsService;
    private ProxyErrorHandlerInterface&MockObject $proxyErrorHandler;
    private ProxyController $controller;

    protected function setUp(): void
    {
        $this->proxyService = $this->createMock(ProxyService::class);
        $this->proxyRequestResolver = $this->createMock(ProxyRequestResolverInterface::class);
        $this->proxyResponseBuilder = $this->createMock(ProxyResponseBuilderInterface::class);
        $this->corsService = $this->createMock(CorsServiceInterface::class);
        $this->proxyErrorHandler = $this->createMock(ProxyErrorHandlerInterface::class);

        $this->controller = new ProxyController(
            $this->proxyService,
            $this->proxyRequestResolver,
            $this->proxyResponseBuilder,
            $this->corsService,
            $this->proxyErrorHandler,
        );
    }

    private function makeProxyRequest(string $path = 'page'): ProxyRequest
    {
        return new ProxyRequest(path: $path, proxyBaseUrl: 'https://proxy.local');
    }

    private function makeProxyResponse(): ProxyResponse
    {
        return new ProxyResponse(content: '', statusCode: 200, contentType: 'text/html', headers: []);
    }

    public function testOptionsRequestReturnsCorsResponse(): void
    {
        $optionsResponse = new Response(status: Response::HTTP_NO_CONTENT);

        $this->corsService
            ->expects($this->once())
            ->method('createOptionsResponse')
            ->willReturn($optionsResponse);

        $result = $this->controller->proxy(Request::create('/path', 'OPTIONS'), 'path');

        $this->assertSame($optionsResponse, $result);
    }

    public function testOptionsDoesNotCallProxyService(): void
    {
        $this->corsService->method('createOptionsResponse')->willReturn(new Response());
        $this->proxyService->expects($this->never())->method('fetchAndModify');

        $this->controller->proxy(Request::create('/path', 'OPTIONS'), 'path');
    }

    public function testGetRequestCallsResolveAndFetchAndBuild(): void
    {
        $request = Request::create('/page', 'GET');
        $proxyRequest = $this->makeProxyRequest();
        $proxyResponse = $this->makeProxyResponse();
        $expectedResponse = new Response('ok', 200);

        $this->proxyRequestResolver
            ->expects($this->once())
            ->method('resolve')
            ->with($request, 'page')
            ->willReturn($proxyRequest);

        $this->proxyService
            ->expects($this->once())
            ->method('fetchAndModify')
            ->with($proxyRequest)
            ->willReturn($proxyResponse);

        $this->proxyResponseBuilder
            ->expects($this->once())
            ->method('build')
            ->with($proxyResponse)
            ->willReturn($expectedResponse);

        $result = $this->controller->proxy($request, 'page');

        $this->assertSame($expectedResponse, $result);
    }

    public function testGetRequestWithDefaultPathPassesEmptyString(): void
    {
        $request = Request::create('/', 'GET');

        $this->proxyRequestResolver
            ->expects($this->once())
            ->method('resolve')
            ->with($request, '')
            ->willReturn($this->makeProxyRequest(''));

        $this->proxyService->method('fetchAndModify')->willReturn($this->makeProxyResponse());
        $this->proxyResponseBuilder->method('build')->willReturn(new Response());

        $this->controller->proxy($request);
    }

    public function testExceptionIsForwardedToErrorHandler(): void
    {
        $proxyRequest = $this->makeProxyRequest();
        $exception = new \RuntimeException('timeout');
        $errorResponse = new Response('Proxy Error', Response::HTTP_BAD_GATEWAY);

        $this->proxyRequestResolver->method('resolve')->willReturn($proxyRequest);
        $this->proxyService->method('fetchAndModify')->willThrowException($exception);

        $this->proxyErrorHandler
            ->expects($this->once())
            ->method('handle')
            ->with($exception, $proxyRequest)
            ->willReturn($errorResponse);

        $result = $this->controller->proxy(Request::create('/bad', 'GET'), 'bad');

        $this->assertSame($errorResponse, $result);
    }

    public function testProxyResponseBuilderIsNotCalledOnException(): void
    {
        $this->proxyRequestResolver->method('resolve')->willReturn($this->makeProxyRequest());
        $this->proxyService->method('fetchAndModify')->willThrowException(new \RuntimeException());
        $this->proxyErrorHandler->method('handle')->willReturn(new Response());

        $this->proxyResponseBuilder->expects($this->never())->method('build');

        $this->controller->proxy(Request::create('/path', 'GET'), 'path');
    }
}