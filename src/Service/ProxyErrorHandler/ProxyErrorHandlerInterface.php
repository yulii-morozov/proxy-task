<?php

declare(strict_types=1);

namespace App\Service\ProxyErrorHandler;

use App\DTO\ProxyRequest;
use Symfony\Component\HttpFoundation\Response;

interface ProxyErrorHandlerInterface
{
    public function handle(\Exception $exception, ProxyRequest $proxyRequest): Response;
}