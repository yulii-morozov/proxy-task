<?php

declare(strict_types=1);

namespace App\Service\CorsService;

use Symfony\Component\HttpFoundation\Response;

final class CorsService implements CorsServiceInterface
{
    private const ALLOW_ORIGIN = '*';
    private const ALLOW_METHODS = 'GET, OPTIONS';
    private const ALLOW_HEADERS = 'Content-Type, Authorization, X-Requested-With';
    private const EXPOSE_HEADERS = 'Content-Disposition, Content-Length, X-Proxy-Target';

    public function addHeaders(Response $response): void
    {
        $response->headers->set('Access-Control-Allow-Origin', self::ALLOW_ORIGIN);
        $response->headers->set('Access-Control-Allow-Methods', self::ALLOW_METHODS);
        $response->headers->set('Access-Control-Allow-Headers', self::ALLOW_HEADERS);
        $response->headers->set('Access-Control-Expose-Headers', self::EXPOSE_HEADERS);
    }

    public function createOptionsResponse(): Response
    {
        $response = new Response(status: Response::HTTP_NO_CONTENT);
        $this->addHeaders($response);
        return $response;
    }
}