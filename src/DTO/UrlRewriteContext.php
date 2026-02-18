<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class UrlRewriteContext
{
    public function __construct(
        public string $targetScheme,
        public string $targetHost,
        public string $proxyBaseUrl
    ) {
    }

    public static function fromTargetUrl(string $targetUrl, string $proxyBaseUrl): self
    {
        $parsed = parse_url($targetUrl);

        return new self(
            targetScheme: $parsed['scheme'] ?? 'https',
            targetHost: $parsed['host'] ?? '',
            proxyBaseUrl: $proxyBaseUrl
        );
    }
}