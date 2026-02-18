<?php

declare(strict_types=1);

namespace App\DTO;

readonly class ProxyResponse
{
    public function __construct(
        public string $content,
        public int $statusCode,
        public string $contentType,
        public array $headers = []
    ) {
    }
}