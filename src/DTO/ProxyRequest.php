<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class ProxyRequest
{
    public function __construct(
        public string $path,
        public string $proxyBaseUrl,
        public ?string $targetHost = null,
        public ?string $queryString = null
    ) {
    }

    public function getFullPath(): string
    {
        return '/' . $this->path . ($this->queryString ? '?' . $this->queryString : '');
    }
}