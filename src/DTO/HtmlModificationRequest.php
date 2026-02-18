<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class HtmlModificationRequest
{
    public function __construct(
        public string $html,
        public string $targetUrl,
        public string $proxyBaseUrl,
        public ?string $subdomainHost = null
    ) {
    }
}