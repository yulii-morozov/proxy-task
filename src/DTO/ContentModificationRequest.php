<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class ContentModificationRequest
{
    public function __construct(
        public string $content,
        public string $contentType,
        public string $targetUrl,
        public string $proxyBaseUrl,
        public ?string $subdomainHost = null
    ) {
    }
}