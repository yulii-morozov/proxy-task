<?php

declare(strict_types=1);

namespace App\Builder;

use App\DTO\ProxyResponse;
use App\Service\CorsService\CorsServiceInterface;
use Symfony\Component\HttpFoundation\Response;

final class ProxyResponseBuilder implements ProxyResponseBuilderInterface
{
    private const PASS_THROUGH_HEADERS = [
        'cache-control',
        'etag',
        'expires',
        'last-modified',
        'content-disposition',
    ];

    public function __construct(
        private readonly CorsServiceInterface $corsService,
    ) {
    }

    public function build(ProxyResponse $proxyResponse): Response
    {
        $response = new Response(
            content: $proxyResponse->content,
            status: $proxyResponse->statusCode,
        );

        $response->headers->set('Content-Type', $proxyResponse->contentType);

        foreach (self::PASS_THROUGH_HEADERS as $header) {
            if (!empty($proxyResponse->headers[$header] ?? null)) {
                $response->headers->set($header, $proxyResponse->headers[$header][0]);
            }
        }

        $this->corsService->addHeaders($response);

        return $response;
    }
}