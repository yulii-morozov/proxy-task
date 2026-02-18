<?php

declare(strict_types=1);

namespace App\Controller;

use App\Builder\ProxyResponseBuilderInterface;
use App\Resolver\ProxyRequestResolverInterface;
use App\Service\CorsService\CorsServiceInterface;
use App\Service\ProxyErrorHandler\ProxyErrorHandlerInterface;
use App\Service\ProxyService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProxyController
{
    public function __construct(
        private readonly ProxyService $proxyService,
        private readonly ProxyRequestResolverInterface $proxyRequestResolver,
        private readonly ProxyResponseBuilderInterface $proxyResponseBuilder,
        private readonly CorsServiceInterface $corsService,
        private readonly ProxyErrorHandlerInterface $proxyErrorHandler,
    ) {
    }

    #[Route('/{path}', name: 'proxy', requirements: ['path' => '.*'], defaults: ['path' => ''], methods: ['GET', 'OPTIONS'])]
    public function proxy(Request $request, string $path = ''): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return $this->corsService->createOptionsResponse();
        }

        $proxyRequest = $this->proxyRequestResolver->resolve($request, $path);

        try {
            $proxyResponse = $this->proxyService->fetchAndModify($proxyRequest);
            return $this->proxyResponseBuilder->build($proxyResponse);
        } catch (\Exception $e) {
            return $this->proxyErrorHandler->handle($e, $proxyRequest);
        }
    }
}