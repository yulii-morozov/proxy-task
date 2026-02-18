<?php

declare(strict_types=1);

namespace App\Service\ProxyErrorHandler;

use App\DTO\ProxyRequest;
use App\Service\CorsService\CorsServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

final class ProxyErrorHandler implements ProxyErrorHandlerInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly CorsServiceInterface $corsService,
    ) {
    }

    public function handle(\Exception $exception, ProxyRequest $proxyRequest): Response
    {
        $this->logger->error('Proxy error', [
            'path' => $proxyRequest->getFullPath() ?? $proxyRequest->path,
            'target_host' => $proxyRequest->targetHost,
            'error' => $exception->getMessage(),
        ]);

        $response = new Response(
            content: 'Proxy Error: ' . $exception->getMessage(),
            status: Response::HTTP_BAD_GATEWAY,
        );

        $this->corsService->addHeaders($response);

        return $response;
    }
}