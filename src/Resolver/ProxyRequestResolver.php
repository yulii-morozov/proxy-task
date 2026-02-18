<?php

declare(strict_types=1);

namespace App\Resolver;

use App\DTO\ProxyRequest;
use Symfony\Component\HttpFoundation\Request;

final class ProxyRequestResolver implements ProxyRequestResolverInterface
{
    private const SUBDOMAIN_MARKER = '_sd_';

    public function resolve(Request $request, string $path): ProxyRequest
    {
        $targetHost = null;
        $actualPath = $path;

        if (str_starts_with($path, self::SUBDOMAIN_MARKER . '/')) {
            $withoutMarker = substr($path, strlen(self::SUBDOMAIN_MARKER) + 1);
            [$targetHost, $actualPath] = array_pad(explode('/', $withoutMarker, 2), 2, '');
        }

        return new ProxyRequest(
            path: $actualPath,
            proxyBaseUrl: $request->getSchemeAndHttpHost(),
            targetHost: $targetHost,
            queryString: $request->getQueryString()
        );
    }
}