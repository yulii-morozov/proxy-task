<?php

declare(strict_types=1);

namespace App\Resolver;

use App\DTO\ProxyRequest;
use Symfony\Component\HttpFoundation\Request;

interface ProxyRequestResolverInterface
{
    public function resolve(Request $request, string $path): ProxyRequest;
}